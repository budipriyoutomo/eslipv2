<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Payslip\GeneratePayslipPdf;
use App\Enums\DeliveryStatus;
use App\Mail\PayslipMail;
use App\Models\DeliveryLog;
use App\Models\Payslip;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generate, send and log one payslip. The PDF is always removed afterwards.
 *
 * Retries: up to 3 real failures (backoff 60s, 300s). Rate-limit releases do not count as failures,
 * so `maxExceptions` + `retryUntil` are used instead of `tries` (DECISIONS D-036).
 */
final class SendPayslipJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $maxExceptions = 3;

    public int $timeout = 120;

    public function __construct(public readonly Payslip $payslip)
    {
        $this->onQueue((string) config('eslip.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('payslip-mail')];
    }

    /** Mailer used for this payslip; the rate limiter throttles per mailer. */
    public function mailerName(): string
    {
        return $this->payslip->batch->company->mailer;
    }

    public function handle(GeneratePayslipPdf $pdf): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $payslip = $this->payslip->fresh(['batch.company']);

        // Deleted meanwhile, or already delivered by an earlier attempt: never email twice.
        if ($payslip === null || $payslip->delivery_status === DeliveryStatus::Sent) {
            return;
        }

        $company = $payslip->batch->company;
        $recipient = $this->recipient($payslip);
        $path = null;

        try {
            $path = $pdf->store($payslip);

            Mail::mailer($company->mailer)
                ->to($recipient)
                ->send(new PayslipMail($payslip, GeneratePayslipPdf::DISK, $path, $pdf->filename($payslip)));

            $payslip->update(['delivery_status' => DeliveryStatus::Sent, 'sent_at' => now()]);
            $this->log($payslip, DeliveryStatus::Sent, $recipient);
        } catch (Throwable $e) {
            $this->log($payslip, DeliveryStatus::Failed, $recipient, $e);

            throw $e;
        } finally {
            if ($path !== null) {
                Storage::disk(GeneratePayslipPdf::DISK)->delete($path);
            }

            $payslip->batch->refreshDeliveryCounts();
        }
    }

    /**
     * Called once all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $payslip = $this->payslip->fresh(['batch']);

        if ($payslip === null || $payslip->delivery_status === DeliveryStatus::Sent) {
            return;
        }

        $payslip->update(['delivery_status' => DeliveryStatus::Failed]);
        $payslip->batch->refreshDeliveryCounts();

        Log::warning('Payslip delivery failed permanently.', [
            'payslip_id' => $payslip->id,
            'payslip_batch_id' => $payslip->payslip_batch_id,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function recipient(Payslip $payslip): string
    {
        $override = config('eslip.mail_override_to');

        return is_string($override) && $override !== '' ? $override : $payslip->email;
    }

    private function log(Payslip $payslip, DeliveryStatus $status, string $recipient, ?Throwable $error = null): void
    {
        DeliveryLog::query()->create([
            'payslip_id' => $payslip->id,
            'status' => $status,
            'recipient' => $recipient,
            'error_message' => $error === null ? null : Str::limit(class_basename($error).': '.$error->getMessage(), 1000),
            'context' => [
                'mailer' => $payslip->batch->company->mailer,
                'attempt' => $this->job?->attempts() ?? 1,
                'employee_email' => $payslip->email,
            ],
            'attempted_at' => now(),
        ]);
    }
}
