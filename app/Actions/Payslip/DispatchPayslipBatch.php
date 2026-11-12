<?php

declare(strict_types=1);

namespace App\Actions\Payslip;

use App\Enums\DeliveryStatus;
use App\Enums\PayslipBatchStatus;
use App\Jobs\SendPayslipJob;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Payslip\Exceptions\CannotDispatchBatch;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Queue one SendPayslipJob per payslip inside a Laravel Bus batch.
 *
 * - send():         first send of a draft batch (all pending payslips).
 * - resendFailed(): requeue only the payslips whose delivery failed.
 */
final readonly class DispatchPayslipBatch
{
    /**
     * @throws CannotDispatchBatch
     */
    public function send(PayslipBatch $batch): Batch
    {
        return $this->dispatch($batch, DeliveryStatus::Pending, requireDraft: true);
    }

    /**
     * @throws CannotDispatchBatch
     */
    public function resendFailed(PayslipBatch $batch): Batch
    {
        return $this->dispatch($batch, DeliveryStatus::Failed, requireDraft: false);
    }

    private function dispatch(PayslipBatch $batch, DeliveryStatus $from, bool $requireDraft): Batch
    {
        $previousStatus = $batch->status;

        /** @var list<int> $payslipIds */
        $payslipIds = DB::transaction(function () use ($batch, $from, $requireDraft, &$previousStatus): array {
            // Lock the row so a double click cannot queue the same batch twice.
            $locked = PayslipBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($requireDraft && $locked->status !== PayslipBatchStatus::Draft) {
                throw CannotDispatchBatch::alreadyDispatched();
            }

            $ids = $locked->payslips()->where('delivery_status', $from)->orderBy('id')->pluck('id')->all();

            if ($ids === []) {
                throw CannotDispatchBatch::nothingToSend();
            }

            $previousStatus = $locked->status;

            if ($from !== DeliveryStatus::Pending) {
                Payslip::query()->whereKey($ids)->update(['delivery_status' => DeliveryStatus::Pending]);
            }

            $locked->update(['status' => PayslipBatchStatus::Processing]);

            return array_values(array_map(intval(...), $ids));
        });

        $jobs = Payslip::query()->whereKey($payslipIds)->orderBy('id')->get()
            ->map(fn (Payslip $payslip): SendPayslipJob => new SendPayslipJob($payslip))
            ->all();

        $batchId = $batch->id;

        try {
            $busBatch = Bus::batch($jobs)
                ->name("payslip-batch-{$batchId}")
                ->onQueue((string) config('eslip.queue'))
                ->allowFailures()
                ->finally(static function () use ($batchId): void {
                    PayslipBatch::query()->find($batchId)?->refreshDeliveryCounts();
                })
                ->dispatch();
        } catch (Throwable $e) {
            // Nothing was queued: undo the status change so the user can simply try again.
            DB::transaction(function () use ($batchId, $payslipIds, $from, $previousStatus): void {
                Payslip::query()->whereKey($payslipIds)->update(['delivery_status' => $from]);
                PayslipBatch::query()->whereKey($batchId)->update(['status' => $previousStatus]);
            });

            throw $e;
        }

        $batch->refresh();
        $batch->update(['job_batch_id' => $busBatch->id]);
        $batch->refreshDeliveryCounts();

        return $busBatch;
    }
}
