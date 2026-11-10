<?php

declare(strict_types=1);

use App\Support\Money;
use Brick\Math\Exception\NumberFormatException;

it('normalizes raw values to two decimals', function (int|float|string|null $raw, string $expected): void {
    expect(Money::normalize($raw))->toBe($expected);
})->with([
    'int' => [5000000, '5000000.00'],
    'float from excel' => [1234567.89, '1234567.89'],
    'float drift' => [0.1 + 0.2, '0.30'],
    'round half up' => ['10.005', '10.01'],
    'numeric string' => [' 250000 ', '250000.00'],
    'negative' => [-1500.5, '-1500.50'],
    'null' => [null, '0.00'],
    'empty string' => ['', '0.00'],
]);

it('rejects non-numeric strings', function (): void {
    Money::normalize('lima juta');
})->throws(NumberFormatException::class);

it('rejects infinite floats', function (): void {
    Money::normalize(INF);
})->throws(InvalidArgumentException::class);

it('sums and subtracts exactly', function (): void {
    expect(Money::sum(['0.10', '0.20', null, 1]))->toBe('1.30')
        ->and(Money::subtract('100.00', '33.33'))->toBe('66.67')
        ->and(Money::equals('100', '100.00'))->toBeTrue();
});

it('formats like the legacy PDF number_format', function (string $amount, string $expected): void {
    expect(Money::format($amount))->toBe($expected)
        ->and(Money::format($amount))->toBe(number_format((float) $amount, 2, ',', '.'));
})->with([
    ['0.00', '0,00'],
    ['1234567.89', '1.234.567,89'],
    ['5000000.00', '5.000.000,00'],
    ['-1500.50', '-1.500,50'],
]);
