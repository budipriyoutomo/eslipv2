<?php

declare(strict_types=1);

use App\Actions\Payslip\DispatchPayslipBatch;
use App\Enums\DeliveryStatus;
use App\Enums\PayslipBatchStatus;
use App\Jobs\SendPayslipJob;
use App\Mail\PayslipMail;
use App\Models\DeliveryLog;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;
use App\Payslip\Exceptions\CannotDispatchBatch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function draftBatch(int $payslips = 3): PayslipBatch
{
    $batch = PayslipBatch::factory()->create(['total_rows' => $payslips]);
    Payslip::factory()->count($payslips)->for($batch, 'batch')->create();

    return $batch;
}

it('queues one job per pending payslip in a bus batch', function (): void {
    Bus::fake();
    $batch = draftBatch(3);

    app(DispatchPayslipBatch::class)->send($batch);

    Bus::assertBatched(fn (PendingBatch $pending): bool => $pending->name === "payslip-batch-{$batch->id}"
        && $pending->jobs->count() === 3
        && $pending->jobs->every(fn ($job): bool => $job instanceof SendPayslipJob)
        && $pending->allowsFailures()
        && $pending->queue() === 'payslips');

    $batch->refresh();
    expect($batch->status)->toBe(PayslipBatchStatus::Processing)
        ->and($batch->job_batch_id)->not->toBeNull();
});

it('refuses to send a batch twice', function (): void {
    Bus::fake();
    $batch = draftBatch();

    app(DispatchPayslipBatch::class)->send($batch);
    app(DispatchPayslipBatch::class)->send($batch->fresh());
})->throws(CannotDispatchBatch::class, 'Batch ini sudah pernah dikirim.');

it('refuses an empty batch', function (): void {
    Bus::fake();

    app(DispatchPayslipBatch::class)->send(PayslipBatch::factory()->create());
})->throws(CannotDispatchBatch::class, 'Tidak ada slip yang perlu dikirim.');

it('resends only failed payslips', function (): void {
    Bus::fake();
    $batch = PayslipBatch::factory()->create(['status' => PayslipBatchStatus::Done, 'total_rows' => 3]);
    Payslip::factory()->for($batch, 'batch')->sent()->create();
    $failed = Payslip::factory()->count(2)->for($batch, 'batch')->failed()->create();
    $batch->refreshDeliveryCounts();

    app(DispatchPayslipBatch::class)->resendFailed($batch);

    Bus::assertBatched(fn (PendingBatch $pending): bool => $pending->jobs->count() === 2
        && $pending->jobs->map(fn (SendPayslipJob $job): int => $job->payslip->id)->sort()->values()->all() === $failed->pluck('id')->sort()->values()->all());

    $batch->refresh();
    expect($batch->status)->toBe(PayslipBatchStatus::Processing)
        ->and($batch->failed_count)->toBe(0)
        ->and($batch->sent_count)->toBe(1)
        ->and($failed->first()->fresh()->delivery_status)->toBe(DeliveryStatus::Pending);
});

it('has nothing to resend when no payslip failed', function (): void {
    Bus::fake();

    app(DispatchPayslipBatch::class)->resendFailed(draftBatch());
})->throws(CannotDispatchBatch::class);

it('sends a whole batch end to end on the sync queue', function (): void {
    Mail::fake();
    Storage::fake('local');
    $batch = draftBatch(3);

    app(DispatchPayslipBatch::class)->send($batch);

    Mail::assertSent(PayslipMail::class, 3);
    $batch->refresh();
    expect($batch->status)->toBe(PayslipBatchStatus::Done)
        ->and($batch->sent_count)->toBe(3)
        ->and($batch->failed_count)->toBe(0)
        ->and($batch->progressPercent())->toBe(100)
        ->and(DeliveryLog::query()->where('status', DeliveryStatus::Sent)->count())->toBe(3)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('sends from the batch page', function (): void {
    Bus::fake();
    $batch = draftBatch(2);

    $this->actingAs(User::factory()->create())
        ->post("/payslip-batches/{$batch->id}/send")
        ->assertRedirect("/payslip-batches/{$batch->id}")
        ->assertSessionHas('status', '2 e-slip masuk antrean pengiriman.');

    Bus::assertBatchCount(1);
});

it('shows an error when sending twice from the page', function (): void {
    Bus::fake();
    $batch = PayslipBatch::factory()->create(['status' => PayslipBatchStatus::Processing]);

    $this->actingAs(User::factory()->create())
        ->from("/payslip-batches/{$batch->id}")
        ->post("/payslip-batches/{$batch->id}/send")
        ->assertRedirect("/payslip-batches/{$batch->id}")
        ->assertSessionHas('error', 'Batch ini sudah pernah dikirim.');

    Bus::assertNothingBatched();
});

it('resends failed payslips from the page', function (): void {
    Bus::fake();
    $batch = PayslipBatch::factory()->create(['status' => PayslipBatchStatus::Done, 'total_rows' => 1]);
    Payslip::factory()->for($batch, 'batch')->failed()->create();

    $this->actingAs(User::factory()->create())
        ->post("/payslip-batches/{$batch->id}/resend-failed")
        ->assertSessionHas('status', '1 e-slip gagal dikirim ulang.');

    Bus::assertBatchCount(1);
});

it('shows send controls and progress on the batch page', function (): void {
    $draft = draftBatch(2);
    $user = User::factory()->create();

    $this->actingAs($user)->get("/payslip-batches/{$draft->id}")
        ->assertSee('Kirim semua')
        ->assertDontSee('Kirim ulang yang gagal')
        ->assertDontSee('http-equiv="refresh"', false);

    $processing = PayslipBatch::factory()->create(['status' => PayslipBatchStatus::Processing, 'total_rows' => 2]);
    Payslip::factory()->for($processing, 'batch')->sent()->create();
    Payslip::factory()->for($processing, 'batch')->failed()->create();
    $processing->refreshDeliveryCounts();

    $this->actingAs($user)->get("/payslip-batches/{$processing->id}")
        ->assertDontSee('Kirim semua')
        ->assertSee('Kirim ulang yang gagal (1)')
        ->assertSee('1 terkirim')
        ->assertSee('1 gagal')
        ->assertSee('aria-valuenow="100"', false);
});
