<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payslip\GeneratePayslipPdf;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use Illuminate\Http\Response;

/**
 * Admin preview: streams the PDF inline without storing it and without the password.
 */
final class PayslipPdfController extends Controller
{
    public function __invoke(PayslipBatch $batch, Payslip $payslip, GeneratePayslipPdf $generate): Response
    {
        $filename = str_replace('"', '', $generate->filename($payslip));

        return response($generate->handle($payslip, protect: false), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
