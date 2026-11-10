<?php

declare(strict_types=1);

namespace App\Actions\Payslip;

use App\Models\Company;
use App\Models\Payslip;
use App\Payslip\Pdf\PayslipDocument;
use App\Payslip\TemplateResolver;
use App\Payslip\Templates\AbstractPayslipTemplate;
use App\Payslip\Templates\PayslipTemplate;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use TCPDF;

/**
 * Render one payslip as an A5 landscape PDF, ported from the legacy FPDF layout
 * (Tmsteslip::genpdf / Tmsteslipxft::genpdf). See DECISIONS D-009, D-010, D-032.
 */
final readonly class GeneratePayslipPdf
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'payslips/tmp';

    /** Values allowed in `companies.pdf_password_field`. */
    public const array PASSWORD_FIELDS = ['nik', 'email', 'none'];

    private const float LEFT = 10;

    private const float RIGHT = 200;

    public function __construct(private TemplateResolver $templates) {}

    /**
     * @param  bool  $protect  false only for the admin preview; emails always get the protected file.
     * @return string PDF binary
     */
    public function handle(Payslip $payslip, bool $protect = true): string
    {
        $payslip->loadMissing('batch.company');
        $batch = $payslip->batch;
        $company = $batch->company;
        $template = $this->templates->resolve($batch->template_type);

        $pdf = $this->newDocument($company, $payslip);

        if ($protect && ($password = $this->password($payslip)) !== null) {
            // TCPDF lists the permissions to REMOVE; printing and copying text stay allowed.
            $pdf->setProtection(['modify', 'annot-forms', 'fill-forms', 'assemble'], $password, Str::random(32), 3);
        }

        $pdf->AddPage();
        $this->header($pdf, $company);
        $this->identity($pdf, $payslip);
        $this->body($pdf, $template, $payslip);
        $this->totals($pdf, $template, $payslip, $company);

        return $pdf->Output('', 'S');
    }

    /**
     * Render the protected PDF to the private disk and return its relative path.
     * The caller (SendPayslipJob) deletes it after sending.
     */
    public function store(Payslip $payslip): string
    {
        $path = sprintf('%s/%d/%s-%d.pdf', self::DIRECTORY, $payslip->payslip_batch_id, Str::slug($payslip->nik), $payslip->id);

        Storage::disk(self::DISK)->put($path, $this->handle($payslip));

        return $path;
    }

    public function password(Payslip $payslip): ?string
    {
        $payslip->loadMissing('batch.company');
        $field = $payslip->batch->company->pdf_password_field;

        $password = match ($field) {
            'nik' => $payslip->nik,
            'email' => $payslip->email,
            'none', '' => null,
            default => throw new InvalidArgumentException("Unknown pdf_password_field [{$field}]."),
        };

        return $password === null || trim($password) === '' ? null : trim($password);
    }

    public function filename(Payslip $payslip): string
    {
        $payslip->loadMissing('batch');

        return sprintf('E-Slip %s %s.pdf', $payslip->nik, $payslip->batch->period->translatedFormat('F Y'));
    }

    private function newDocument(Company $company, Payslip $payslip): PayslipDocument
    {
        $pdf = new PayslipDocument;
        $pdf->SetMargins(self::LEFT, 10, 210 - self::RIGHT);
        $pdf->SetAutoPageBreak(true, 10);
        // FPDF's default cell margin is 1mm left/right; keep text where the legacy slip had it.
        $pdf->setCellPaddings(1, 0, 1, 0);
        $pdf->SetCreator((string) config('app.name'));
        $pdf->SetAuthor($company->name);
        $pdf->SetTitle('E-Slip '.$payslip->nik);

        return $pdf;
    }

    private function header(TCPDF $pdf, Company $company): void
    {
        $logo = $company->logo_path;
        if ($logo !== null && Storage::disk(self::DISK)->exists($logo)) {
            $pdf->Image(Storage::disk(self::DISK)->path($logo), self::LEFT, 10, 0, 14);
        }

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(25, 10);
        $pdf->Cell(120, 5, $company->name, 0, 1);

        $pdf->SetFont('helvetica', '', 9);
        foreach ([$company->address, $company->city, $company->phone] as $line) {
            $pdf->SetX(25);
            $pdf->Cell(120, 5, (string) $line, 0, 1, 'L');
        }

        // Legacy drew this at y=30, touching the phone line; 1mm lower keeps it readable.
        $pdf->Line(self::LEFT, 31, self::RIGHT, 31);
        $pdf->SetY(31);
    }

    private function identity(TCPDF $pdf, Payslip $payslip): void
    {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(100, 7, __('payslip.fields.nik').' : '.$payslip->nik, 0, 0, 'L');
        $pdf->Cell(0, 7, __('payslip.period').' : '.$payslip->batch->period->translatedFormat('F Y'), 0, 1, 'R');
        $pdf->Cell(100, 6, __('payslip.fields.name').' : '.$payslip->name, 0, 0, 'L');
        $pdf->Cell(0, 6, __('payslip.fields.position').' : '.($payslip->position ?? '-'), 0, 1, 'L');

        $this->rule($pdf);
        $pdf->Cell(80, 6, __('payslip.earnings'), 0, 0, 'L');
        $pdf->Cell(0, 6, __('payslip.deductions'), 0, 1, 'L');
        $this->rule($pdf);
    }

    /**
     * Two columns, row by row, exactly like the legacy slip:
     *   left  = earnings, "KET PENERIMAAN :", earning note
     *   right = deductions, "KET POTONGAN :", deduction note, attendance counts
     */
    private function body(TCPDF $pdf, PayslipTemplate $template, Payslip $payslip): void
    {
        $left = [];
        foreach ($template->earnings($payslip) as $line) {
            $left[] = [$line->label, Money::format($line->amount)];
        }
        $left[] = [AbstractPayslipTemplate::label('earning_note').' :', null];
        $left[] = [(string) $payslip->earning_note, null];

        $right = [];
        foreach ($template->deductions($payslip) as $line) {
            $right[] = [$line->label, Money::format($line->amount)];
        }
        $right[] = [AbstractPayslipTemplate::label('deduction_note').' :', null];
        $right[] = [(string) $payslip->deduction_note, null];
        foreach (AbstractPayslipTemplate::ATTENDANCE_FIELDS as $field) {
            $right[] = [AbstractPayslipTemplate::label($field), (string) ($payslip->attendance[$field] ?? 0)];
        }

        $pdf->SetFont('helvetica', '', 8);

        for ($i = 0, $rows = max(count($left), count($right)); $i < $rows; $i++) {
            [$label, $value] = $left[$i] ?? ['', null];
            if ($value === null) {
                $pdf->Cell(80, 5, $label, 0, 0, 'L', false, '', 1);
            } else {
                $pdf->Cell(40, 5, $label, 0, 0, 'L');
                $pdf->Cell(40, 5, ' = '.$value, 0, 0, 'L');
            }

            [$label, $value] = $right[$i] ?? ['', null];
            if ($value === null) {
                $pdf->Cell(0, 5, $label, 0, 1, 'L', false, '', 1);
            } else {
                $pdf->Cell(55, 5, $label, 0, 0, 'L');
                $pdf->Cell(0, 5, ' = '.$value, 0, 1, 'L');
            }
        }
    }

    private function totals(TCPDF $pdf, PayslipTemplate $template, Payslip $payslip, Company $company): void
    {
        $pdf->SetY($pdf->GetY() + 1);
        $this->rule($pdf);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, __('payslip.total_earnings'), 0, 0, 'L');
        $pdf->Cell(40, 6, ' = '.Money::format($template->totalEarnings($payslip)), 0, 0, 'L');
        $pdf->Cell(55, 6, __('payslip.total_deductions'), 0, 0, 'L');
        $pdf->Cell(0, 6, ' = '.Money::format($template->totalDeductions($payslip)), 0, 1, 'L');
        $this->rule($pdf);

        $place = trim((string) $company->city);
        $date = $payslip->batch->issued_at->translatedFormat('d F Y');

        $pdf->Cell(40, 6, __('payslip.take_home_pay'), 0, 0, 'L');
        $pdf->Cell(40, 6, ' = '.Money::format($payslip->take_home_pay), 0, 0, 'L');
        $pdf->Cell(0, 6, $place === '' ? $date : "{$place}, {$date}", 0, 1, 'R');
    }

    private function rule(TCPDF $pdf): void
    {
        $pdf->Line(self::LEFT, $pdf->GetY(), self::RIGHT, $pdf->GetY());
    }
}
