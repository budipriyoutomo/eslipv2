<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PayslipBatchStatus;
use App\Models\Company;
use App\Models\PayslipBatch;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $companyId = $request->integer('company_id') ?: null;
        $status = $request->enum('status', PayslipBatchStatus::class);

        $batches = PayslipBatch::query()
            ->with(['company', 'uploader'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard', [
            'batches' => $batches,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => PayslipBatchStatus::cases(),
            'filters' => ['company_id' => $companyId, 'status' => $status],
        ]);
    }
}
