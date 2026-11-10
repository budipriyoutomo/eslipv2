<?php

declare(strict_types=1);

use App\Models\Payslip;
use App\Models\User;

it('streams an unprotected preview inline', function (): void {
    $payslip = Payslip::factory()->create(['nik' => '1001']);

    $response = $this->actingAs(User::factory()->create())
        ->get("/payslip-batches/{$payslip->payslip_batch_id}/payslips/{$payslip->id}/pdf");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline; filename="E-Slip 1001 ')
        ->and($response->getContent())->toStartWith('%PDF-')
        ->and($response->getContent())->not->toContain('/Encrypt');
});

it('only previews payslips that belong to the batch', function (): void {
    $payslip = Payslip::factory()->create();
    $otherBatchPayslip = Payslip::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get("/payslip-batches/{$payslip->payslip_batch_id}/payslips/{$otherBatchPayslip->id}/pdf")
        ->assertNotFound();
});

it('requires login for previews', function (): void {
    $payslip = Payslip::factory()->create();

    $this->get("/payslip-batches/{$payslip->payslip_batch_id}/payslips/{$payslip->id}/pdf")
        ->assertRedirect('/login');
});

it('links each payslip preview from the batch page', function (): void {
    $payslip = Payslip::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get("/payslip-batches/{$payslip->payslip_batch_id}")
        ->assertOk()
        ->assertSee("/payslip-batches/{$payslip->payslip_batch_id}/payslips/{$payslip->id}/pdf", false);
});
