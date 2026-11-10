<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payslip\DispatchPayslipBatch;
use App\Models\PayslipBatch;
use App\Payslip\Exceptions\CannotDispatchBatch;
use Illuminate\Http\RedirectResponse;

/**
 * Legacy: every role could press "proses" (genpdf), so every authenticated user may send.
 */
final class PayslipBatchDispatchController extends Controller
{
    public function send(PayslipBatch $batch, DispatchPayslipBatch $dispatch): RedirectResponse
    {
        try {
            $busBatch = $dispatch->send($batch);
        } catch (CannotDispatchBatch $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('payslip-batches.show', $batch)
            ->with('status', "{$busBatch->totalJobs} e-slip masuk antrean pengiriman.");
    }

    public function resendFailed(PayslipBatch $batch, DispatchPayslipBatch $dispatch): RedirectResponse
    {
        try {
            $busBatch = $dispatch->resendFailed($batch);
        } catch (CannotDispatchBatch $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('payslip-batches.show', $batch)
            ->with('status', "{$busBatch->totalJobs} e-slip gagal dikirim ulang.");
    }
}
