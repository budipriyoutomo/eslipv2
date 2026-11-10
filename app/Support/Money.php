<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Decimal-string helpers for payroll amounts (scale 2). Never use float arithmetic on amounts.
 */
final class Money
{
    public const int SCALE = 2;

    /**
     * Normalize a raw value (Excel cell, DB value) to a decimal string with 2 decimals.
     * Null and empty string are treated as zero, like the legacy app did.
     */
    public static function normalize(int|float|string|null $value): string
    {
        return self::of($value)->toString();
    }

    public static function of(int|float|string|null $value): BigDecimal
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return BigDecimal::zero()->toScale(self::SCALE);
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Amount must be a finite number.');
            }

            // Excel stores numbers as IEEE doubles; 6 decimals keeps them exact before rounding to cents.
            $value = sprintf('%.6F', $value);
        }

        return BigDecimal::of(is_string($value) ? trim($value) : $value)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * @param  iterable<int|float|string|null>  $values
     */
    public static function sum(iterable $values): string
    {
        $total = BigDecimal::zero()->toScale(self::SCALE);

        foreach ($values as $value) {
            $total = $total->plus(self::of($value));
        }

        return $total->toString();
    }

    public static function subtract(string $a, string $b): string
    {
        return self::of($a)->minus(self::of($b))->toScale(self::SCALE)->toString();
    }

    public static function equals(string $a, string $b): bool
    {
        return self::of($a)->isEqualTo(self::of($b));
    }

    /**
     * Format like the legacy PDF: number_format($x, 2, ',', '.') → 1.234.567,89
     */
    public static function format(string $amount): string
    {
        $value = self::of($amount);
        $negative = $value->isNegative();
        [$integer, $fraction] = explode('.', $value->abs()->toString());

        return ($negative ? '-' : '').number_format((float) $integer, 0, ',', '.').','.$fraction;
    }
}
