<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Models\DeliveryLog;
use App\Models\PayslipBatch;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Delivery history: every send attempt, plus history imported from the legacy `tstatus` table.
 */
final class DeliveryLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $status = $request->enum('status', DeliveryStatus::class);
        $batch = $request->filled('batch') ? PayslipBatch::query()->with('company')->find($request->integer('batch')) : null;

        $logs = DeliveryLog::query()
            ->with('payslip.batch.company')
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($batch, fn (Builder $query) => $query->whereHas(
                'payslip',
                fn (Builder $payslip) => $payslip->where('payslip_batch_id', $batch?->id),
            ))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->whereLike('recipient', '%'.$search.'%')
                ->orWhereHas('payslip', fn (Builder $payslip) => $payslip
                    ->whereLike('nik', '%'.$search.'%')
                    ->orWhereLike('name', '%'.$search.'%')
                    ->orWhereLike('email', '%'.$search.'%'))
                // Legacy rows keep their data in the JSON context column (cast to text for Postgres).
                ->orWhere(fn (Builder $legacy) => $legacy
                    ->whereNull('payslip_id')
                    ->whereLike($this->contextAsText($legacy), '%'.$search.'%'))))
            ->orderByDesc('attempted_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('delivery-logs.index', [
            'logs' => $logs,
            'search' => $search,
            'status' => $status,
            'statuses' => DeliveryStatus::cases(),
            'batch' => $batch,
        ]);
    }

    /**
     * @param  Builder<DeliveryLog>  $query
     */
    private function contextAsText(Builder $query): Expression|string
    {
        return $query->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? $query->getQuery()->raw('context::text')
            : 'context';
    }
}
