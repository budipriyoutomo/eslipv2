<?php

declare(strict_types=1);

use App\Enums\PayslipBatchStatus;
use App\Models\Company;
use App\Models\PayslipBatch;
use App\Models\User;

it('lists batches with company, period and counters', function (): void {
    $company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $batch = PayslipBatch::factory()->for($company)->create([
        'period' => '2026-09-01',
        'total_rows' => 12,
        'sent_count' => 10,
        'failed_count' => 2,
        'status' => PayslipBatchStatus::Done,
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('PT Maju Jaya')
        ->assertSee('September 2026')
        ->assertSeeInOrder(['12', '10', '2', 'Selesai'])
        ->assertSee("/payslip-batches/{$batch->id}", false);
});

it('shows the newest period first', function (): void {
    $company = Company::factory()->create();
    PayslipBatch::factory()->for($company)->create(['period' => '2026-08-01']);
    PayslipBatch::factory()->for($company)->create(['period' => '2026-10-01']);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertSeeInOrder(['Oktober 2026', 'Agustus 2026']);
});

it('filters batches by company and status', function (): void {
    $a = Company::factory()->create(['name' => 'PT Alpha']);
    $b = Company::factory()->create(['name' => 'PT Beta']);
    $draft = PayslipBatch::factory()->for($a)->create(['status' => PayslipBatchStatus::Draft]);
    $done = PayslipBatch::factory()->for($b)->create(['status' => PayslipBatchStatus::Done]);

    $user = User::factory()->create();

    $this->actingAs($user)->get("/dashboard?company_id={$a->id}")
        ->assertSee("/payslip-batches/{$draft->id}\"", false)
        ->assertDontSee("/payslip-batches/{$done->id}\"", false);

    $this->actingAs($user)->get('/dashboard?status=done')
        ->assertDontSee("/payslip-batches/{$draft->id}\"", false)
        ->assertSee("/payslip-batches/{$done->id}\"", false);
});

it('shows an empty state', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertSee('Belum ada batch.');
});

it('shows management links only to managers', function (): void {
    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertSee('Riwayat')
        ->assertDontSee('href="'.url('/companies').'"', false)
        ->assertDontSee('href="'.url('/users').'"', false);

    $this->actingAs(User::factory()->admin()->create())->get('/dashboard')
        ->assertSee('href="'.url('/companies').'"', false)
        ->assertSee('href="'.url('/users').'"', false);
});
