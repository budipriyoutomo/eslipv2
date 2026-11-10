<?php

declare(strict_types=1);

namespace App\Payslip\Data;

/**
 * One labelled amount on a payslip (e.g. GAJI = 5000000.00).
 */
final readonly class PayslipLine
{
    public function __construct(
        public string $field,
        public string $label,
        public string $amount,
    ) {}
}
