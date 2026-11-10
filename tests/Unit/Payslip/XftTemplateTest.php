<?php

declare(strict_types=1);

use App\Enums\PayslipTemplateType;
use App\Models\Payslip;
use App\Payslip\Data\PayslipLine;
use App\Payslip\Templates\XftTemplate;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    $this->template = new XftTemplate;
});

/**
 * One Excel row as the legacy reader saw it (1-based columns), see Tmsteslipxft::proses().
 *
 * @return array<int, mixed>
 */
function xftRow(): array
{
    return [
        1 => '2001', 2 => 'Budi', 3 => 'Barista', 4 => 'ignored-period',
        5 => 4500000, 6 => 500000, 7 => 100000, 8 => 350000, 9 => 250000,
        10 => 0, 11 => 90000, 12 => 45000, 13 => 45000, 14 => 0, 15 => 20000,
        16 => 'Kasbon', 17 => 15000, 18 => 'Insentif', 19 => 0, 20 => 1, 21 => 0, 22 => 24,
        23 => 5515000, 24 => 'ignored-issuer', 25 => 'ignored-company', 26 => 'budi@example.com',
    ];
}

it('identifies as the xft template', function (): void {
    expect($this->template->type())->toBe(PayslipTemplateType::Xft);
});

it('maps the legacy Excel columns', function (): void {
    expect($this->template->columnMap())->toBe([
        1 => 'nik', 2 => 'name', 3 => 'position',
        5 => 'base_salary', 6 => 'bonus', 7 => 'overtime', 8 => 'meal_allowance',
        9 => 'transport_allowance', 10 => 'mcu_refund',
        11 => 'bpjs_jht', 12 => 'bpjs_jp', 13 => 'bpjs_health', 14 => 'mcu_deduction',
        15 => 'other_deductions', 16 => 'deduction_note', 17 => 'other_earnings', 18 => 'earning_note',
        19 => 'sick', 20 => 'absent', 21 => 'sudden_leave', 22 => 'present',
        23 => 'take_home_pay', 26 => 'email',
    ]);
});

it('picks mapped cells out of a row and skips ignored columns', function (): void {
    $row = $this->template->mapRow(xftRow());

    expect($row)->toHaveCount(23)
        ->and($row['bonus'])->toBe(500000)
        ->and($row['other_deductions'])->toBe(20000)
        ->and($row['take_home_pay'])->toBe(5515000)
        ->and($row['email'])->toBe('budi@example.com')
        ->and($row)->not->toHaveKeys(['fixed_service', 'variable_service', 'target', 'safety_shoes_penalty'])
        ->and($row)->not->toContain('ignored-period', 'ignored-issuer', 'ignored-company');
});

it('lists earnings in legacy PDF order', function (): void {
    $labels = array_map(fn (PayslipLine $l): string => $l->label, $this->template->earnings(new Payslip(['earnings' => [], 'deductions' => []])));

    expect($labels)->toBe([
        'GAJI', 'BONUS', 'LEMBUR', 'UANG MAKAN', 'TRANSPORT', 'PENGEMBALIAN MCU', 'PENERIMAAN LAINNYA',
    ]);
});

it('lists deductions in legacy PDF order', function (): void {
    $labels = array_map(fn (PayslipLine $l): string => $l->label, $this->template->deductions(new Payslip(['earnings' => [], 'deductions' => []])));

    expect($labels)->toBe([
        'BPJS KETENAGAKERJAAN JHT', 'BPJS KETENAGAKERJAAN JP', 'BPJS KESEHATAN', 'POTONGAN MCU', 'POTONGAN LAIN',
    ]);
});

it('computes totals like the legacy PDF, without penalty', function (): void {
    $payslip = new Payslip([
        'earnings' => [
            'base_salary' => '4500000.00', 'bonus' => '500000.00', 'overtime' => '100000.00',
            'meal_allowance' => '350000.00', 'transport_allowance' => '250000.00',
            'mcu_refund' => '0.00', 'other_earnings' => '15000.00',
        ],
        'deductions' => [
            'bpjs_jht' => '90000.00', 'bpjs_jp' => '45000.00', 'bpjs_health' => '45000.00',
            'mcu_deduction' => '0.00', 'other_deductions' => '20000.00',
            'safety_shoes_penalty' => '150000.00', // not an XFT field: must be ignored
        ],
    ]);

    expect($this->template->totalEarnings($payslip))->toBe('5715000.00')
        ->and($this->template->totalDeductions($payslip))->toBe('200000.00')
        ->and($this->template->computedTakeHomePay($payslip))->toBe('5515000.00');
});

it('accepts a valid mapped row', function (): void {
    expect(Validator::make($this->template->mapRow(xftRow()), $this->template->rules())->passes())->toBeTrue();
});

it('has rules for every mapped field', function (): void {
    expect(array_keys($this->template->rules()))->toBe(array_values($this->template->columnMap()));
});
