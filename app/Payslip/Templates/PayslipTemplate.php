<?php

declare(strict_types=1);

namespace App\Payslip\Templates;

use App\Enums\PayslipTemplateType;
use App\Models\Payslip;
use App\Payslip\Data\PayslipLine;

/**
 * Strategy per legacy payslip layout (Standard, XFT, ...). See DECISIONS D-005 / D-007.
 */
interface PayslipTemplate
{
    public function type(): PayslipTemplateType;

    /**
     * Excel column (1-based, as in the legacy reader) => field name.
     * Ignored legacy columns (period, issuer, company) are not listed.
     *
     * @return array<int, string>
     */
    public function columnMap(): array;

    /**
     * Validation rules per field, applied to one mapped Excel row.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array;

    /**
     * Earning fields in legacy PDF order.
     *
     * @return list<string>
     */
    public function earningFields(): array;

    /**
     * Deduction fields in legacy PDF order.
     *
     * @return list<string>
     */
    public function deductionFields(): array;

    /** @return list<PayslipLine> */
    public function earnings(Payslip $payslip): array;

    /** @return list<PayslipLine> */
    public function deductions(Payslip $payslip): array;

    /** Decimal string, computed like the legacy PDF. */
    public function totalEarnings(Payslip $payslip): string;

    /** Decimal string, computed like the legacy PDF. */
    public function totalDeductions(Payslip $payslip): string;

    /** Total earnings minus total deductions; compared against the Excel THP (warning only). */
    public function computedTakeHomePay(Payslip $payslip): string;

    /**
     * Pick the mapped fields out of one Excel row (1-based column index => raw cell value).
     *
     * @param  array<int, mixed>  $cells
     * @return array<string, mixed>
     */
    public function mapRow(array $cells): array;
}
