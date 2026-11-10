<?php

declare(strict_types=1);

use App\Actions\Payslip\ImportPayslipBatch;
use App\Enums\DeliveryStatus;
use App\Enums\PayslipBatchStatus;
use App\Enums\PayslipTemplateType;
use App\Models\Company;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;
use App\Payslip\Import\PayslipImportFailed;
use Carbon\CarbonImmutable;
use Tests\Support\PayslipSpreadsheet;

function importFile(string $path, PayslipTemplateType $type = PayslipTemplateType::Standard): PayslipBatch
{
    return app(ImportPayslipBatch::class)->handle(
        company: Company::factory()->template($type)->create(),
        templateType: $type,
        period: CarbonImmutable::parse('2026-10-15'),
        issuedAt: CarbonImmutable::parse('2026-10-31'),
        path: $path,
        originalFilename: 'oktober.xls',
        uploader: User::factory()->create(),
    );
}

/**
 * @return list<string>
 */
function importErrors(callable $callback): array
{
    try {
        $callback();
    } catch (PayslipImportFailed $e) {
        return $e->messages();
    }

    test()->fail('Expected PayslipImportFailed.');

    return [];
}

it('imports a standard .xls file into a draft batch', function (): void {
    $batch = importFile(PayslipSpreadsheet::write([
        PayslipSpreadsheet::standardRow('1001'),
        PayslipSpreadsheet::standardRow('1002', 'cici@example.com'),
    ]));

    expect($batch->status)->toBe(PayslipBatchStatus::Draft)
        ->and($batch->total_rows)->toBe(2)
        ->and($batch->period->toDateString())->toBe('2026-10-01')
        ->and($batch->issued_at->toDateString())->toBe('2026-10-31')
        ->and($batch->source_filename)->toBe('oktober.xls')
        ->and($batch->warnings)->toBeNull()
        ->and($batch->payslips)->toHaveCount(2);

    $payslip = $batch->payslips->first()->fresh();
    expect($payslip->nik)->toBe('1001')
        ->and($payslip->name)->toBe('Andi')
        ->and($payslip->position)->toBe('Cook')
        ->and($payslip->email)->toBe('andi@example.com')
        ->and($payslip->earnings)->toBe([
            'base_salary' => '5000000.00', 'fixed_service' => '750000.00', 'variable_service' => '250000.50',
            'target' => '100000.00', 'overtime' => '120000.00', 'meal_allowance' => '400000.00',
            'transport_allowance' => '300000.00', 'mcu_refund' => '50000.00', 'other_earnings' => '20000.00',
        ])
        ->and($payslip->deductions)->toBe([
            'bpjs_jht' => '100000.00', 'bpjs_jp' => '50000.00', 'bpjs_health' => '40000.00',
            'mcu_deduction' => '25000.00', 'safety_shoes_penalty' => '150000.00', 'other_deductions' => '10000.00',
        ])
        ->and($payslip->deduction_note)->toBe('Kasbon')
        ->and($payslip->earning_note)->toBe('Insentif')
        ->and($payslip->attendance)->toBe(['sick' => 1, 'absent' => 0, 'sudden_leave' => 2, 'present' => 23])
        ->and($payslip->take_home_pay)->toBe('6615000.50')
        ->and($payslip->delivery_status)->toBe(DeliveryStatus::Pending)
        ->and($payslip->raw_row[28])->toBe('PT Lama');
});

it('imports an xft .xlsx file', function (): void {
    $batch = importFile(PayslipSpreadsheet::write([PayslipSpreadsheet::xftRow()], 'xlsx'), PayslipTemplateType::Xft);

    $payslip = $batch->payslips->first()->fresh();
    expect($batch->template_type)->toBe(PayslipTemplateType::Xft)
        ->and($payslip->earnings['bonus'])->toBe('500000.00')
        ->and($payslip->deductions)->not->toHaveKey('safety_shoes_penalty')
        ->and($payslip->take_home_pay)->toBe('5515000.00')
        ->and($payslip->email)->toBe('budi@example.com');
});

it('stops at the first row with an empty NIK, like the legacy reader', function (): void {
    $batch = importFile(PayslipSpreadsheet::write([
        PayslipSpreadsheet::standardRow('1001'),
        [2 => 'baris kosong NIK'],
        PayslipSpreadsheet::standardRow('1003'),
    ]));

    expect($batch->payslips()->pluck('nik')->all())->toBe(['1001']);
});

it('skips a NIK header row', function (): void {
    $batch = importFile(PayslipSpreadsheet::write([
        [1 => 'NIK', 2 => 'NAMA', 3 => 'JABATAN', 5 => 'GAJI'],
        PayslipSpreadsheet::standardRow('1001'),
    ]));

    expect($batch->payslips()->pluck('nik')->all())->toBe(['1001']);
});

it('keeps numeric NIKs as plain strings', function (): void {
    $batch = importFile(PayslipSpreadsheet::write([
        array_replace(PayslipSpreadsheet::standardRow(), [1 => 1001]),
        array_replace(PayslipSpreadsheet::standardRow(), [1 => 3273010101900001]),
    ]));

    expect($batch->payslips()->pluck('nik')->all())->toBe(['1001', '3273010101900001']);
});

it('treats blank amounts as zero', function (): void {
    $row = array_replace(PayslipSpreadsheet::standardRow(), [8 => null, 20 => null, 26 => 6495000.5]);

    $payslip = importFile(PayslipSpreadsheet::write([$row]))->payslips->first();

    expect($payslip->earnings['target'])->toBe('0.00')
        ->and($payslip->earnings['other_earnings'])->toBe('0.00');
});

it('reads calculated values of formula cells', function (): void {
    $row = array_replace(PayslipSpreadsheet::xftRow(), [23 => '=SUM(E1:G1,H1:J1,Q1)-SUM(K1:O1)']);

    $payslip = importFile(PayslipSpreadsheet::write([$row], 'xlsx'), PayslipTemplateType::Xft)->payslips->first();

    expect($payslip->take_home_pay)->toBe('5515000.00');
});

it('collects every invalid row and stores nothing', function (): void {
    $errors = importErrors(fn () => importFile(PayslipSpreadsheet::write([
        PayslipSpreadsheet::standardRow('1001'),
        array_replace(PayslipSpreadsheet::standardRow('1002'), [29 => 'bukan-email']),
        PayslipSpreadsheet::standardRow('1003'),
        array_replace(PayslipSpreadsheet::standardRow('1004'), [5 => 'lima juta', 26 => null]),
    ])));

    expect($errors)->toBe([
        'Baris 2 (NIK 1002): Email (kolom 29) harus berupa alamat email yang valid.',
        'Baris 4 (NIK 1004): GAJI (kolom 5) harus berupa angka.',
        'Baris 4 (NIK 1004): TAKE HOME PAY (kolom 26) wajib diisi.',
    ])
        ->and(PayslipBatch::query()->count())->toBe(0)
        ->and(Payslip::query()->count())->toBe(0);
});

it('rejects a file without data rows', function (): void {
    expect(importErrors(fn () => importFile(PayslipSpreadsheet::write([[2 => 'tanpa NIK']]))))
        ->toBe(['File tidak berisi data: kolom 1 (NIK) pada baris pertama kosong.']);
});

it('rejects a file that is not a spreadsheet', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'eslip').'.xls';
    file_put_contents($path, 'bukan excel');

    expect(importErrors(fn () => importFile($path))[0])->toStartWith('File tidak bisa dibaca');
});

it('warns but still imports when the Excel THP does not match', function (): void {
    $batch = importFile(PayslipSpreadsheet::write([
        array_replace(PayslipSpreadsheet::standardRow('1001'), [26 => 6000000]),
    ]));

    expect($batch->total_rows)->toBe(1)
        ->and($batch->warnings)->toBe([
            'Baris 1 (NIK 1001): THP di Excel 6.000.000,00 berbeda dengan total penerimaan − total potongan 6.615.000,50.',
        ])
        ->and($batch->payslips->first()->take_home_pay)->toBe('6000000.00');
});

it('warns about duplicate NIKs', function (): void {
    $batch = importFile(PayslipSpreadsheet::write([
        PayslipSpreadsheet::standardRow('1001'),
        PayslipSpreadsheet::standardRow('1001'),
    ]));

    expect($batch->total_rows)->toBe(2)
        ->and($batch->warnings)->toBe(['Baris 2 (NIK 1001): NIK sama dengan baris 1.']);
});
