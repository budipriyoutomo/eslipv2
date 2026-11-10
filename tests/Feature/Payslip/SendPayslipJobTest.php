<?php

declare(strict_types=1);

use App\Actions\Payslip\GeneratePayslipPdf;
use App\Enums\DeliveryStatus;
use App\Enums\PayslipBatchStatus;
use App\Jobs\SendPayslipJob;
use App\Mail\PayslipMail;
use App\Models\Company;
use App\Models\DeliveryLog;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use Illuminate\Mail\Attachment;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function queuedPayslip(array $companyAttributes = [], array $payslipAttributes = []): Payslip
{
    $company = Company::factory()->create([
        'name' => 'PT Sumber Rasa',
        'mailer' => 'smtp_bogajabar',
        'from_address' => 'hrd@sumberrasa.test',
        'from_name' => 'HRD Sumber Rasa',
        ...$companyAttributes,
    ]);
    $batch = PayslipBatch::factory()->for($company)->create([
        'period' => '2026-10-01',
        'status' => PayslipBatchStatus::Processing,
        'total_rows' => 1,
    ]);

    return Payslip::factory()->for($batch, 'batch')->create([
        'nik' => '1001',
        'name' => 'Andi Wijaya',
        'email' => 'andi@example.com',
        ...$payslipAttributes,
    ]);
}

function runJob(Payslip $payslip): void
{
    (new SendPayslipJob($payslip))->handle(app(GeneratePayslipPdf::class));
}

it('sends the payslip through the company mailer and logs it', function (): void {
    Mail::fake();
    $payslip = queuedPayslip();

    runJob($payslip);

    Mail::assertSent(PayslipMail::class, function (PayslipMail $mail) use ($payslip): bool {
        return $mail->hasTo('andi@example.com')
            && $mail->mailer === 'smtp_bogajabar'
            && $mail->hasSubject('E-Slip PT Sumber Rasa')
            && $mail->hasFrom('hrd@sumberrasa.test', 'HRD Sumber Rasa')
            && $mail->payslip->is($payslip)
            && $mail->pdfFilename === 'E-Slip 1001 Oktober 2026.pdf';
    });

    $payslip->refresh();
    expect($payslip->delivery_status)->toBe(DeliveryStatus::Sent)
        ->and($payslip->sent_at)->not->toBeNull();

    $log = DeliveryLog::query()->sole();
    expect($log->payslip_id)->toBe($payslip->id)
        ->and($log->status)->toBe(DeliveryStatus::Sent)
        ->and($log->recipient)->toBe('andi@example.com')
        ->and($log->error_message)->toBeNull()
        ->and($log->context['mailer'])->toBe('smtp_bogajabar');

    $batch = $payslip->batch->fresh();
    expect($batch->sent_count)->toBe(1)
        ->and($batch->status)->toBe(PayslipBatchStatus::Done);
});

it('deletes the temporary PDF after sending', function (): void {
    Mail::fake();

    runJob(queuedPayslip());

    expect(Storage::disk('local')->allFiles(GeneratePayslipPdf::DIRECTORY))->toBe([]);
});

it('renders the legacy subject and body', function (): void {
    $payslip = queuedPayslip();
    Storage::disk('local')->put('payslips/tmp/x.pdf', '%PDF-1.7');

    $mail = new PayslipMail($payslip, 'local', 'payslips/tmp/x.pdf', 'E-Slip 1001 Oktober 2026.pdf');

    $mail->assertHasSubject('E-Slip PT Sumber Rasa');
    $mail->assertSeeInHtml('Dear Andi Wijaya,');
    $mail->assertSeeInHtml('Berikut E-Slip Gaji Bulan Oktober 2026.');
    $mail->assertSeeInHtml('gunakan NIK Anda');
    $mail->assertSeeInText('Berikut E-Slip Gaji Bulan Oktober 2026.');
    $mail->assertHasAttachment(
        Attachment::fromStorageDisk('local', 'payslips/tmp/x.pdf')->as('E-Slip 1001 Oktober 2026.pdf')->withMime('application/pdf'),
    );
});

it('omits the password hint when the PDF is not protected', function (): void {
    $payslip = queuedPayslip(['pdf_password_field' => 'none']);

    (new PayslipMail($payslip, 'local', 'x.pdf', 'x.pdf'))->assertDontSeeInHtml('password');
});

it('sends to the override address during a parallel run', function (): void {
    Mail::fake();
    config(['eslip.mail_override_to' => 'test-inbox@example.com']);

    runJob(queuedPayslip());

    Mail::assertSent(PayslipMail::class, fn (PayslipMail $mail): bool => $mail->hasTo('test-inbox@example.com') && ! $mail->hasTo('andi@example.com'));
    expect(DeliveryLog::query()->sole())
        ->recipient->toBe('test-inbox@example.com')
        ->context->toMatchArray(['employee_email' => 'andi@example.com']);
});

it('never sends a payslip twice', function (): void {
    Mail::fake();
    $payslip = queuedPayslip(payslipAttributes: ['delivery_status' => DeliveryStatus::Sent, 'sent_at' => now()]);

    runJob($payslip);

    Mail::assertNothingSent();
    expect(DeliveryLog::query()->count())->toBe(0);
});

it('logs a failed attempt, cleans up and rethrows for retry', function (): void {
    $payslip = queuedPayslip(['mailer' => 'mailer_tidak_ada']);

    expect(fn () => runJob($payslip))->toThrow(InvalidArgumentException::class, 'Mailer [mailer_tidak_ada] is not defined.');

    $log = DeliveryLog::query()->sole();
    expect($log->status)->toBe(DeliveryStatus::Failed)
        ->and($log->error_message)->toContain('Mailer [mailer_tidak_ada] is not defined.')
        ->and($payslip->fresh()->delivery_status)->toBe(DeliveryStatus::Pending)
        ->and(Storage::disk('local')->allFiles(GeneratePayslipPdf::DIRECTORY))->toBe([]);
});

it('marks the payslip failed once retries are exhausted', function (): void {
    $payslip = queuedPayslip();

    (new SendPayslipJob($payslip))->failed(new RuntimeException('SMTP timeout'));

    $batch = $payslip->batch->fresh();
    expect($payslip->fresh()->delivery_status)->toBe(DeliveryStatus::Failed)
        ->and($batch->failed_count)->toBe(1)
        ->and($batch->status)->toBe(PayslipBatchStatus::Done);
});

it('does not mark a delivered payslip as failed', function (): void {
    $payslip = queuedPayslip(payslipAttributes: ['delivery_status' => DeliveryStatus::Sent]);

    (new SendPayslipJob($payslip))->failed(new RuntimeException('late failure'));

    expect($payslip->fresh()->delivery_status)->toBe(DeliveryStatus::Sent);
});

it('retries three failures with backoff and throttles per mailer', function (): void {
    config(['eslip.mail_per_minute' => 15]);
    $job = new SendPayslipJob(queuedPayslip());

    expect($job->maxExceptions)->toBe(3)
        ->and($job->backoff())->toBe([60, 300])
        ->and($job->queue)->toBe('payslips')
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);

    $limit = RateLimiter::limiter('payslip-mail')($job);
    expect($limit->maxAttempts)->toBe(15)
        ->and($limit->key)->toBe('payslip-mail:smtp_bogajabar');
});
