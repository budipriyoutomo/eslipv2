<?php

declare(strict_types=1);

use App\Enums\PayslipBatchStatus;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;

it('lets managers delete a draft batch with its payslips', function (): void {
    $batch = PayslipBatch::factory()->create();
    Payslip::factory()->count(2)->for($batch, 'batch')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get("/payslip-batches/{$batch->id}")
        ->assertSee('Hapus batch');

    $this->actingAs(User::factory()->admin()->create())
        ->delete("/payslip-batches/{$batch->id}")
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Batch draft berhasil dihapus.');

    expect($batch->fresh())->toBeNull()
        ->and(Payslip::query()->count())->toBe(0);
});

it('does not let the user role delete batches, like the legacy app', function (): void {
    $batch = PayslipBatch::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get("/payslip-batches/{$batch->id}")
        ->assertDontSee('Hapus batch');

    $this->actingAs(User::factory()->create())
        ->delete("/payslip-batches/{$batch->id}")
        ->assertForbidden();
});

it('never deletes a batch that was already sent', function (PayslipBatchStatus $status): void {
    $batch = PayslipBatch::factory()->create(['status' => $status]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete("/payslip-batches/{$batch->id}")
        ->assertForbidden();

    expect($batch->fresh())->not->toBeNull();
})->with([PayslipBatchStatus::Processing, PayslipBatchStatus::Done]);

it('filters payslips on the batch page by delivery status', function (): void {
    $batch = PayslipBatch::factory()->create(['status' => PayslipBatchStatus::Done]);
    Payslip::factory()->for($batch, 'batch')->sent()->create(['name' => 'Sudah Terkirim']);
    Payslip::factory()->for($batch, 'batch')->failed()->create(['name' => 'Gagal Kirim']);

    $this->actingAs(User::factory()->create())
        ->get("/payslip-batches/{$batch->id}?status=failed")
        ->assertSee('Gagal Kirim')
        ->assertDontSee('Sudah Terkirim');
});
