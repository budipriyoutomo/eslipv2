<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\Company;
use App\Models\DeliveryLog;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;

function loggedPayslip(string $nik, string $name, string $company = 'PT Sumber Rasa'): Payslip
{
    $batch = PayslipBatch::factory()
        ->for(Company::factory()->create(['name' => $company]))
        ->create(['period' => '2026-10-01']);

    return Payslip::factory()->for($batch, 'batch')->create(['nik' => $nik, 'name' => $name, 'email' => strtolower($name).'@example.com']);
}

it('lists delivery attempts with payslip details', function (): void {
    $payslip = loggedPayslip('1001', 'Andi');
    DeliveryLog::factory()->for($payslip)->create(['recipient' => 'andi@example.com']);
    DeliveryLog::factory()->for($payslip)->failed('Connection to SMTP refused')->create();

    $this->actingAs(User::factory()->create())
        ->get('/delivery-logs')
        ->assertOk()
        ->assertSee('1001')
        ->assertSee('Andi')
        ->assertSee('PT Sumber Rasa · Oktober 2026')
        ->assertSee('Terkirim')
        ->assertSee('Gagal')
        ->assertSee('Connection to SMTP refused');
});

it('shows legacy history rows from the context column', function (): void {
    DeliveryLog::factory()->create([
        'payslip_id' => null,
        'status' => DeliveryStatus::Sent,
        'recipient' => 'lama@example.com',
        'context' => ['source' => 'tstatus', 'nik' => '9001', 'name' => 'Karyawan Lama', 'period' => '2019-01-01', 'company' => 'PT Lama'],
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/delivery-logs')
        ->assertSee('9001')
        ->assertSee('Karyawan Lama')
        ->assertSee('PT Lama · Januari 2019')
        ->assertSee('sistem lama');
});

it('searches by NIK, name, email and legacy data', function (): void {
    DeliveryLog::factory()->for(loggedPayslip('1001', 'Andi'))->create();
    DeliveryLog::factory()->for(loggedPayslip('2002', 'Budi'))->create();
    DeliveryLog::factory()->create(['payslip_id' => null, 'context' => ['nik' => '9001', 'name' => 'Citra']]);

    $user = User::factory()->create();

    $this->actingAs($user)->get('/delivery-logs?q=1001')->assertSee('Andi')->assertDontSee('Budi')->assertDontSee('Citra');
    $this->actingAs($user)->get('/delivery-logs?q=budi')->assertSee('Budi')->assertDontSee('Andi');
    $this->actingAs($user)->get('/delivery-logs?q=Citra')->assertSee('9001')->assertDontSee('Andi');
    $this->actingAs($user)->get('/delivery-logs?q=cITRA')->assertSee('9001')->assertDontSee('Andi');
});

it('filters by status and batch', function (): void {
    $andi = loggedPayslip('1001', 'Andi');
    $budi = loggedPayslip('2002', 'Budi');
    DeliveryLog::factory()->for($andi)->create();
    DeliveryLog::factory()->for($budi)->failed()->create();

    $user = User::factory()->create();

    $this->actingAs($user)->get('/delivery-logs?status=failed')->assertSee('Budi')->assertDontSee('Andi');
    $this->actingAs($user)->get("/delivery-logs?batch={$andi->payslip_batch_id}")
        ->assertSee('Andi')
        ->assertDontSee('Budi')
        ->assertSee('tampilkan semua');
});

it('links each batch page to its delivery history', function (): void {
    $payslip = loggedPayslip('1001', 'Andi');

    $this->actingAs(User::factory()->create())
        ->get("/payslip-batches/{$payslip->payslip_batch_id}")
        ->assertSee("/delivery-logs?batch={$payslip->payslip_batch_id}", false);
});
