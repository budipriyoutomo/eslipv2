<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payslip\ImportPayslipBatch;
use App\Enums\DeliveryStatus;
use App\Enums\PayslipBatchStatus;
use App\Http\Requests\StorePayslipBatchRequest;
use App\Models\Company;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;
use App\Payslip\Import\PayslipImportFailed;
use App\Payslip\TemplateResolver;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

final class PayslipBatchController extends Controller
{
    public function create(TemplateResolver $templates): View
    {
        return view('payslip-batches.create', [
            'companies' => Company::query()->orderBy('name')->get(),
            'templates' => $templates->supported(),
        ]);
    }

    public function store(StorePayslipBatchRequest $request, ImportPayslipBatch $import): RedirectResponse
    {
        $file = $request->file('file');
        assert($file instanceof UploadedFile);

        $uploader = $request->user();
        assert($uploader instanceof User);

        try {
            $batch = $import->handle(
                company: $request->company(),
                templateType: $request->templateType(),
                period: $request->period(),
                issuedAt: $request->issuedAt(),
                path: $file->getRealPath() ?: $file->getPathname(),
                originalFilename: $file->getClientOriginalName(),
                uploader: $uploader,
            );
        } catch (PayslipImportFailed $e) {
            return back()->withInput()->with('import_errors', $e->messages());
        }

        return redirect()
            ->route('payslip-batches.show', $batch)
            ->with('status', "{$batch->total_rows} slip berhasil diimport. Periksa preview sebelum mengirim.");
    }

    public function show(Request $request, PayslipBatch $batch, TemplateResolver $templates): View
    {
        $batch->load(['company', 'uploader']);
        $template = $templates->resolve($batch->template_type);
        $status = $request->enum('status', DeliveryStatus::class);

        $payslips = $batch->payslips()
            ->when($status, fn ($query) => $query->where('delivery_status', $status))
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $rows = $payslips->getCollection()->map(fn (Payslip $payslip): array => [
            'payslip' => $payslip,
            'total_earnings' => $template->totalEarnings($payslip),
            'total_deductions' => $template->totalDeductions($payslip),
        ]);

        return view('payslip-batches.show', [
            'batch' => $batch,
            'payslips' => $payslips,
            'rows' => $rows,
            'totalTakeHomePay' => Money::normalize((string) $batch->payslips()->sum('take_home_pay')),
            'statusFilter' => $status,
            'statuses' => DeliveryStatus::cases(),
            'canDelete' => $this->canDelete($request, $batch),
        ]);
    }

    /**
     * Legacy `deldata`: discard a wrong upload. Only drafts (nothing sent yet), only roles that may delete.
     */
    public function destroy(Request $request, PayslipBatch $batch): RedirectResponse
    {
        abort_unless($this->canDelete($request, $batch), 403);

        $batch->delete();

        return redirect()->route('dashboard')->with('status', 'Batch draft berhasil dihapus.');
    }

    private function canDelete(Request $request, PayslipBatch $batch): bool
    {
        $user = $request->user();

        return $user instanceof User
            && $user->role->canDelete()
            && $batch->status === PayslipBatchStatus::Draft;
    }
}
