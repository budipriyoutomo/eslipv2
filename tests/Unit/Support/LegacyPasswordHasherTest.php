<?php

declare(strict_types=1);

use App\Support\LegacyPasswordHasher;

it('verifies an unsalted sha1 hash', function (): void {
    $hasher = new LegacyPasswordHasher;

    expect($hasher->check('rahasia', sha1('rahasia')))->toBeTrue()
        ->and($hasher->check('salah', sha1('rahasia')))->toBeFalse();
});

it('accepts uppercase hashes', function (): void {
    expect((new LegacyPasswordHasher)->check('rahasia', strtoupper(sha1('rahasia'))))->toBeTrue();
});

it('mimics the legacy trim rule on input', function (): void {
    expect((new LegacyPasswordHasher)->check('  rahasia ', sha1('rahasia')))->toBeTrue();
});

it('rejects values that are not sha1 hashes', function (string $stored): void {
    expect((new LegacyPasswordHasher)->check($stored, $stored))->toBeFalse();
})->with([
    'plaintext' => ['rahasia'],
    'empty' => [''],
    'bcrypt' => ['$2y$04$abcdefghijklmnopqrstuuJ9n0k5W3Z1lH7m0X2Q8qWq1x2y3z4a5b'],
]);
