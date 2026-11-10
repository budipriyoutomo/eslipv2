<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\DeliveryLog;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;
use Illuminate\Database\QueryException;

it('links company, batch, payslip and delivery logs', function (): void {
    $uploader = User::factory()->create();
    $company = Company::factory()->create();
    $batch = PayslipBatch::factory()->for($company)->for($uploader, 'uploader')->create();
    $payslips = Payslip::factory()->count(3)->for($batch, 'batch')->create();
    DeliveryLog::factory()->count(2)->for($payslips->first())->create();

    expect($company->payslipBatches)->toHaveCount(1)
        ->and($batch->company->is($company))->toBeTrue()
        ->and($batch->uploader->is($uploader))->toBeTrue()
        ->and($uploader->uploadedBatches)->toHaveCount(1)
        ->and($batch->payslips)->toHaveCount(3)
        ->and($payslips->first()->batch->is($batch))->toBeTrue()
        ->and($payslips->first()->deliveryLogs)->toHaveCount(2)
        ->and(DeliveryLog::query()->first()->payslip->is($payslips->first()))->toBeTrue();
});

it('cascades payslips and logs when a batch is deleted', function (): void {
    $payslip = Payslip::factory()->create();
    DeliveryLog::factory()->for($payslip)->create();

    $payslip->batch->delete();

    expect(Payslip::query()->count())->toBe(0)
        ->and(DeliveryLog::query()->count())->toBe(0);
});

it('keeps the batch when its uploader is deleted', function (): void {
    $batch = PayslipBatch::factory()->create();

    $batch->uploader->delete();

    expect($batch->fresh()->uploaded_by)->toBeNull();
});

it('refuses to delete a company that still has batches', function (): void {
    $batch = PayslipBatch::factory()->create();

    $batch->company->delete();
})->throws(QueryException::class);
