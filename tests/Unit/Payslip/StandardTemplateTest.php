<?php

declare(strict_types=1);

use App\Enums\PayslipTemplateType;
use App\Models\Payslip;
use App\Payslip\Data\PayslipLine;
use App\Payslip\Templates\StandardTemplate;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    $this->template = new StandardTemplate;
});

/**
 * One Excel row as the legacy reader saw it (1-based columns), see Tmsteslip::proses().
 *
 * @return array<int, mixed>
 */
function standardRow(): array
{
    return [
        1 => '1001', 2 => 'Andi', 3 => 'Cook', 4 => 'ignored-period',
        5 => 5000000, 6 => 750000, 7 => 250000.5, 8 => 100000, 9 => 120000,
        10 => 400000, 11 => 300000, 12 => 50000, 13 => 100000, 14 => 50000,
        15 => 40000, 16 => 25000, 17 => 150000, 18 => 10000, 19 => 'Kasbon',
        20 => 20000, 21 => 'Insentif', 22 => 1, 23 => 0, 24 => 2, 25 => 23,
        26 => 6615000.5, 27 => 'ignored-issuer', 28 => 'ignored-company', 29 => 'andi@example.com',
    ];
}

it('identifies as the standard template', function (): void {
    expect($this->template->type())->toBe(PayslipTemplateType::Standard);
});

it('maps the legacy Excel columns', function (): void {
    expect($this->template->columnMap())->toBe([
        1 => 'nik', 2 => 'name', 3 => 'position',
        5 => 'base_salary', 6 => 'fixed_service', 7 => 'variable_service', 8 => 'target',
        9 => 'overtime', 10 => 'meal_allowance', 11 => 'transport_allowance', 12 => 'mcu_refund',
        13 => 'bpjs_jht', 14 => 'bpjs_jp', 15 => 'bpjs_health', 16 => 'mcu_deduction',
        17 => 'safety_shoes_penalty', 18 => 'other_deductions', 19 => 'deduction_note',
        20 => 'other_earnings', 21 => 'earning_note',
        22 => 'sick', 23 => 'absent', 24 => 'sudden_leave', 25 => 'present',
        26 => 'take_home_pay', 29 => 'email',
    ]);
});

it('picks mapped cells out of a row and skips ignored columns', function (): void {
    $row = $this->template->mapRow(standardRow());

    expect($row)->toHaveCount(26)
        ->and($row['nik'])->toBe('1001')
        ->and($row['base_salary'])->toBe(5000000)
        ->and($row['safety_shoes_penalty'])->toBe(150000)
        ->and($row['deduction_note'])->toBe('Kasbon')
        ->and($row['take_home_pay'])->toBe(6615000.5)
        ->and($row['email'])->toBe('andi@example.com')
        ->and($row)->not->toContain('ignored-period', 'ignored-issuer', 'ignored-company');
});

it('treats missing cells as null', function (): void {
    $row = $this->template->mapRow([1 => '1001']);

    expect($row['base_salary'])->toBeNull()->and($row['email'])->toBeNull();
});

it('lists earnings in legacy PDF order', function (): void {
    $labels = array_map(fn (PayslipLine $l): string => $l->label, $this->template->earnings(new Payslip(['earnings' => [], 'deductions' => []])));

    expect($labels)->toBe([
        'GAJI', 'SERVICE TETAP', 'SERVICE VARIABEL', 'TARGET', 'LEMBUR',
        'UANG MAKAN', 'TRANSPORT', 'PENGEMBALIAN MCU', 'PENERIMAAN LAINNYA',
    ]);
});

it('lists deductions in legacy PDF order', function (): void {
    $labels = array_map(fn (PayslipLine $l): string => $l->label, $this->template->deductions(new Payslip(['earnings' => [], 'deductions' => []])));

    expect($labels)->toBe([
        'BPJS KETENAGAKERJAAN JHT', 'BPJS KETENAGAKERJAAN JP', 'BPJS KESEHATAN',
        'POTONGAN MCU', 'PINALTY SAFETY SHOES', 'POTONGAN LAIN',
    ]);
});

it('computes totals like the legacy PDF, including the penalty', function (): void {
    $payslip = new Payslip([
        'earnings' => [
            'base_salary' => '5000000.00', 'fixed_service' => '750000.00', 'variable_service' => '250000.50',
            'target' => '100000.00', 'overtime' => '120000.00', 'meal_allowance' => '400000.00',
            'transport_allowance' => '300000.00', 'mcu_refund' => '50000.00', 'other_earnings' => '20000.00',
            'bonus' => '999999.00', // not a Standard field: must be ignored
        ],
        'deductions' => [
            'bpjs_jht' => '100000.00', 'bpjs_jp' => '50000.00', 'bpjs_health' => '40000.00',
            'mcu_deduction' => '25000.00', 'safety_shoes_penalty' => '150000.00', 'other_deductions' => '10000.00',
        ],
    ]);

    expect($this->template->totalEarnings($payslip))->toBe('6990000.50')
        ->and($this->template->totalDeductions($payslip))->toBe('375000.00')
        ->and($this->template->computedTakeHomePay($payslip))->toBe('6615000.50');
});

it('treats missing amounts as zero', function (): void {
    $payslip = new Payslip(['earnings' => ['base_salary' => '100.00'], 'deductions' => []]);

    expect($this->template->totalEarnings($payslip))->toBe('100.00')
        ->and($this->template->totalDeductions($payslip))->toBe('0.00')
        ->and($this->template->earnings($payslip)[1]->amount)->toBe('0.00');
});

it('adds decimals without float drift', function (): void {
    $payslip = new Payslip([
        'earnings' => ['base_salary' => '0.10', 'overtime' => '0.20'],
        'deductions' => [],
    ]);

    expect($this->template->totalEarnings($payslip))->toBe('0.30');
});

it('has rules for every mapped field', function (): void {
    expect(array_keys($this->template->rules()))->toBe(array_values($this->template->columnMap()));
});

it('accepts a valid mapped row', function (): void {
    $row = $this->template->mapRow(standardRow());

    expect(Validator::make($row, $this->template->rules())->passes())->toBeTrue();
});

it('accepts blank amounts like the legacy app', function (): void {
    $row = [...$this->template->mapRow(standardRow()), 'target' => null, 'other_earnings' => ''];

    expect(Validator::make($row, $this->template->rules())->passes())->toBeTrue();
});

it('rejects invalid rows', function (string $field, mixed $value): void {
    $row = [...$this->template->mapRow(standardRow()), $field => $value];

    expect(Validator::make($row, $this->template->rules())->errors()->keys())->toBe([$field]);
})->with([
    'missing nik' => ['nik', null],
    'missing name' => ['name', ''],
    'bad email' => ['email', 'bukan-email'],
    'missing email' => ['email', null],
    'text amount' => ['base_salary', 'lima juta'],
    'missing thp' => ['take_home_pay', null],
    'negative attendance' => ['sick', -1],
    'fractional attendance' => ['present', 1.5],
]);
