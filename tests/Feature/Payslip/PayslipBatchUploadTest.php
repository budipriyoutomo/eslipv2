<?php

declare(strict_types=1);

use App\Enums\PayslipTemplateType;
use App\Models\Company;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Support\PayslipSpreadsheet;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function uploadPayload(Company $company, array $overrides = []): array
{
    return [
        'company_id' => $company->id,
        'period' => '2026-10',
        'issued_at' => '2026-10-31',
        'file' => PayslipSpreadsheet::upload([PayslipSpreadsheet::standardRow()]),
        ...$overrides,
    ];
}

it('requires login', function (): void {
    $this->get('/payslip-batches/create')->assertRedirect('/login');
});

it('shows the upload form with only supported templates', function (): void {
    Company::factory()->create(['name' => 'PT Maju']);

    $this->actingAs(User::factory()->create())
        ->get('/payslip-batches/create')
        ->assertOk()
        ->assertSee('PT Maju')
        ->assertSee('<option value="standard"', false)
        ->assertSee('<option value="xft"', false)
        ->assertDontSee('<option value="nd"', false)
        ->assertDontSee('<option value="llao"', false);
});

it('lets every role upload, like the legacy app', function (): void {
    $company = Company::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->post('/payslip-batches', uploadPayload($company));

    $batch = PayslipBatch::query()->sole();
    $response->assertRedirect("/payslip-batches/{$batch->id}")
        ->assertSessionHas('status', '1 slip berhasil diimport. Periksa preview sebelum mengirim.');

    expect($batch->template_type)->toBe(PayslipTemplateType::Standard)
        ->and($batch->source_filename)->toBe('eslip-oktober.xls')
        ->and($batch->period->toDateString())->toBe('2026-10-01');
});

it('uses the company default template when none is chosen', function (): void {
    $company = Company::factory()->template(PayslipTemplateType::Xft)->create();

    $this->actingAs(User::factory()->create())
        ->post('/payslip-batches', uploadPayload($company, [
            'file' => PayslipSpreadsheet::upload([PayslipSpreadsheet::xftRow()], 'xlsx'),
        ]))
        ->assertSessionHasNoErrors();

    expect(PayslipBatch::query()->sole()->template_type)->toBe(PayslipTemplateType::Xft);
});

it('lets the upload override the company template', function (): void {
    $company = Company::factory()->template(PayslipTemplateType::Standard)->create();

    $this->actingAs(User::factory()->create())
        ->post('/payslip-batches', uploadPayload($company, [
            'template_type' => 'xft',
            'file' => PayslipSpreadsheet::upload([PayslipSpreadsheet::xftRow()]),
        ]))
        ->assertSessionHasNoErrors();

    expect(PayslipBatch::query()->sole()->template_type)->toBe(PayslipTemplateType::Xft);
});

it('rejects templates without an implementation', function (): void {
    $company = Company::factory()->template(PayslipTemplateType::Nd)->create();

    $this->actingAs(User::factory()->create())
        ->post('/payslip-batches', uploadPayload($company))
        ->assertSessionHasErrors(['template_type' => 'Template ND belum didukung.']);

    expect(PayslipBatch::query()->count())->toBe(0);
});

it('validates the upload form', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->create())
        ->post('/payslip-batches', uploadPayload(Company::factory()->create(), $overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing company' => [['company_id' => null], 'company_id'],
    'unknown company' => [['company_id' => 999], 'company_id'],
    'bad template' => [['template_type' => 'payroll'], 'template_type'],
    'period not Y-m' => [['period' => '10/2026'], 'period'],
    'period full date' => [['period' => '2026-10-01'], 'period'],
    'issued_at not a date' => [['issued_at' => '31-10-2026'], 'issued_at'],
    'missing file' => [['file' => null], 'file'],
    'csv file' => [['file' => UploadedFile::fake()->create('gaji.csv', 10, 'text/csv')], 'file'],
    'too large' => [['file' => UploadedFile::fake()->create('gaji.xlsx', 10241)], 'file'],
]);

it('returns row errors to the form without saving', function (): void {
    $company = Company::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from('/payslip-batches/create')
        ->post('/payslip-batches', uploadPayload($company, [
            'file' => PayslipSpreadsheet::upload([array_replace(PayslipSpreadsheet::standardRow('1001'), [29 => null])]),
        ]))
        ->assertRedirect('/payslip-batches/create')
        ->assertSessionHas('import_errors', ['Baris 1 (NIK 1001): Email (kolom 29) wajib diisi.']);

    expect(PayslipBatch::query()->count())->toBe(0);

    $this->get('/payslip-batches/create')->assertSee('Baris 1 (NIK 1001): Email (kolom 29) wajib diisi.');
});

it('previews a batch with totals and warnings', function (): void {
    $company = Company::factory()->create(['name' => 'PT Preview']);

    $this->actingAs(User::factory()->create())
        ->post('/payslip-batches', uploadPayload($company, [
            'file' => PayslipSpreadsheet::upload([
                PayslipSpreadsheet::standardRow('1001'),
                array_replace(PayslipSpreadsheet::standardRow('1002', 'cici@example.com'), [26 => 6000000]),
            ]),
        ]));

    $batch = PayslipBatch::query()->sole();

    $this->get("/payslip-batches/{$batch->id}")
        ->assertOk()
        ->assertSee('PT Preview')
        ->assertSee('Oktober 2026')
        ->assertSee('31 Oktober 2026')
        ->assertSee('cici@example.com')
        ->assertSee('6.990.000,50')   // total penerimaan
        ->assertSee('375.000,00')     // total potongan
        ->assertSee('12.615.000,50')  // total THP batch
        ->assertSee('1 peringatan')
        ->assertSee('THP di Excel 6.000.000,00');

    expect(Payslip::query()->count())->toBe(2);
});
