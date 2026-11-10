<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Legacy subject "E-Slip {PT}" and body "Dear ..., Berikut E-Slip Gaji Bulan {periode}" (DECISIONS D-009).
 * Sent synchronously from SendPayslipJob, never queued itself.
 */
final class PayslipMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly Payslip $payslip,
        public readonly string $pdfDisk,
        public readonly string $pdfPath,
        public readonly string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->payslip->batch->company;

        return new Envelope(
            from: new Address($company->from_address, $company->from_name),
            subject: 'E-Slip '.$company->name,
        );
    }

    public function content(): Content
    {
        $payslip = $this->payslip;

        return new Content(
            view: 'mail.payslip',
            text: 'mail.payslip-text',
            with: [
                'name' => $payslip->name,
                'period' => $payslip->batch->period->translatedFormat('F Y'),
                'companyName' => $payslip->batch->company->name,
                'passwordHint' => $this->passwordHint(),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk($this->pdfDisk, $this->pdfPath)
                ->as($this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }

    private function passwordHint(): ?string
    {
        return match ($this->payslip->batch->company->pdf_password_field) {
            'nik' => 'File PDF dilindungi password: gunakan NIK Anda.',
            'email' => 'File PDF dilindungi password: gunakan alamat email Anda.',
            default => null,
        };
    }
}
