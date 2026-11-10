<?php

declare(strict_types=1);

use App\Enums\UserRole;

it('maps legacy role names', function (string $legacy, UserRole $expected): void {
    expect(UserRole::fromLegacy($legacy))->toBe($expected);
})->with([
    ['SuperAdmin', UserRole::SuperAdmin],
    ['Admin', UserRole::Admin],
    ['User', UserRole::User],
    ['', UserRole::User],
]);

it('allows delete for every role except user', function (): void {
    expect(UserRole::SuperAdmin->canDelete())->toBeTrue()
        ->and(UserRole::Admin->canDelete())->toBeTrue()
        ->and(UserRole::User->canDelete())->toBeFalse();
});
