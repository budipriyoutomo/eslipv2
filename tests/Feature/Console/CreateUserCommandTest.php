<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a super admin that can log in', function (): void {
    $this->artisan('eslip:create-user', ['username' => 'hrd', '--password' => 'rahasia123'])
        ->assertSuccessful();

    $user = User::query()->where('username', 'hrd')->sole();

    expect($user->role)->toBe(UserRole::SuperAdmin)
        ->and($user->name)->toBe('hrd')
        ->and($user->legacy_sha1)->toBeFalse()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();

    $this->post('/login', ['username' => 'HRD', 'password' => 'rahasia123'])->assertRedirect('/dashboard');
});

it('rejects a duplicate username regardless of case', function (): void {
    User::factory()->create(['username' => 'admin']);

    $this->artisan('eslip:create-user', ['username' => 'Admin', '--password' => 'rahasia123'])
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('rejects a short password and an unknown role', function (array $options): void {
    $this->artisan('eslip:create-user', ['username' => 'hrd', ...$options])->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'short password' => [['--password' => 'pendek']],
    'unknown role' => [['--password' => 'rahasia123', '--role' => 'owner']],
]);
