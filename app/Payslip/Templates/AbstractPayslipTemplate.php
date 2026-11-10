<?php

declare(strict_types=1);

namespace App\Payslip\Templates;

use App\Models\Payslip;
use App\Payslip\Data\PayslipLine;
use App\Support\Money;

/**
 * Shared behaviour: concrete templates only declare their column map and field order.
 * Totals are the sum of exactly the listed earning / deduction fields, as in the legacy PDF.
 */
abstract class AbstractPayslipTemplate implements PayslipTemplate
{
    /** @var list<string> */
    public const array IDENTITY_FIELDS = ['nik', 'name', 'position', 'email'];

    /** @var list<string> */
    public const array NOTE_FIELDS = ['earning_note', 'deduction_note'];

    /** @var list<string> */
    public const array ATTENDANCE_FIELDS = ['sick', 'absent', 'sudden_leave', 'present'];

    public const string TAKE_HOME_PAY = 'take_home_pay';

    public function rules(): array
    {
        $rules = [];
        $moneyFields = [...$this->earningFields(), ...$this->deductionFields()];

        foreach ($this->columnMap() as $field) {
            $rules[$field] = match (true) {
                $field === 'nik' => ['required', 'string', 'max:30'],
                $field === 'name' => ['required', 'string', 'max:255'],
                $field === 'position' => ['nullable', 'string', 'max:255'],
                $field === 'email' => ['required', 'email', 'max:255'],
                $field === self::TAKE_HOME_PAY => ['required', 'numeric'],
                in_array($field, $moneyFields, true) => ['nullable', 'numeric'],
                in_array($field, self::NOTE_FIELDS, true) => ['nullable', 'string', 'max:1000'],
                in_array($field, self::ATTENDANCE_FIELDS, true) => ['nullable', 'integer', 'min:0'],
                default => ['nullable'],
            };
        }

        return $rules;
    }

    public function earnings(Payslip $payslip): array
    {
        return $this->lines($this->earningFields(), $payslip->earnings);
    }

    public function deductions(Payslip $payslip): array
    {
        return $this->lines($this->deductionFields(), $payslip->deductions);
    }

    public function totalEarnings(Payslip $payslip): string
    {
        return Money::sum(array_map(fn (PayslipLine $line): string => $line->amount, $this->earnings($payslip)));
    }

    public function totalDeductions(Payslip $payslip): string
    {
        return Money::sum(array_map(fn (PayslipLine $line): string => $line->amount, $this->deductions($payslip)));
    }

    public function computedTakeHomePay(Payslip $payslip): string
    {
        return Money::subtract($this->totalEarnings($payslip), $this->totalDeductions($payslip));
    }

    public function mapRow(array $cells): array
    {
        $row = [];

        foreach ($this->columnMap() as $column => $field) {
            $row[$field] = $cells[$column] ?? null;
        }

        return $row;
    }

    public static function label(string $field): string
    {
        return __('payslip.fields.'.$field);
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, string>  $amounts
     * @return list<PayslipLine>
     */
    private function lines(array $fields, array $amounts): array
    {
        return array_map(
            fn (string $field): PayslipLine => new PayslipLine($field, self::label($field), Money::normalize($amounts[$field] ?? null)),
            $fields,
        );
    }
}
