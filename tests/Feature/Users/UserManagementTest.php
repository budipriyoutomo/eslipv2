<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('denies the user role access to user management', function (string $method, string $uri): void {
    $target = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->call($method, str_replace('{id}', (string) $target->id, $uri))
        ->assertForbidden();
})->with([
    ['GET', '/users'],
    ['GET', '/users/create'],
    ['POST', '/users'],
    ['GET', '/users/{id}/edit'],
    ['PUT', '/users/{id}'],
    ['DELETE', '/users/{id}'],
]);

it('lets managers list users', function (UserRole $role): void {
    User::factory()->create(['username' => 'karyawan1']);

    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/users')
        ->assertOk()
        ->assertSee('karyawan1');
})->with([UserRole::SuperAdmin, UserRole::Admin]);

it('searches users', function (): void {
    User::factory()->create(['username' => 'andi']);
    User::factory()->create(['username' => 'budi']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/users?q=and')
        ->assertSee('andi')
        ->assertDontSee('budi');
});

it('creates a user with a bcrypt password', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/users', [
            'username' => 'baru',
            'name' => 'User Baru',
            'email' => 'baru@example.com',
            'role' => UserRole::User->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect('/users');

    $user = User::query()->where('username', 'baru')->sole();
    expect($user->role)->toBe(UserRole::User)
        ->and($user->legacy_sha1)->toBeFalse()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});

it('validates new users', function (): void {
    User::factory()->create(['username' => 'ada']);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/users', [
            'username' => 'ada',
            'name' => '',
            'role' => 'boss',
            'password' => 'short',
            'password_confirmation' => 'beda',
        ])
        ->assertSessionHasErrors(['username', 'name', 'role', 'password']);
});

it('treats usernames differing only in case as duplicates', function (): void {
    $existing = User::factory()->create(['username' => 'Ada']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/users', [
            'username' => 'ada',
            'name' => 'Ada Lain',
            'role' => UserRole::User->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors(['username']);

    $this->actingAs($admin)
        ->put("/users/{$existing->id}", [
            'username' => 'ADA',
            'name' => 'Ada',
            'role' => UserRole::User->value,
        ])
        ->assertSessionHasNoErrors();

    expect($existing->refresh()->username)->toBe('ADA');
});

it('prevents admins from creating super admins', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/users', [
            'username' => 'naik',
            'name' => 'Naik Pangkat',
            'role' => UserRole::SuperAdmin->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('role');

    expect(User::query()->where('username', 'naik')->exists())->toBeFalse();
});

it('lets super admins create super admins', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'username' => 'boss2',
            'name' => 'Boss Dua',
            'role' => UserRole::SuperAdmin->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect('/users');

    expect(User::query()->where('username', 'boss2')->sole()->role)->toBe(UserRole::SuperAdmin);
});

it('updates a user without changing the password when left blank', function (): void {
    $target = User::factory()->create(['username' => 'lama']);
    $oldHash = $target->password;

    $this->actingAs(User::factory()->admin()->create())
        ->put("/users/{$target->id}", [
            'username' => 'lama',
            'name' => 'Nama Baru',
            'email' => '',
            'role' => UserRole::Admin->value,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect('/users');

    $target->refresh();
    expect($target->name)->toBe('Nama Baru')
        ->and($target->role)->toBe(UserRole::Admin)
        ->and($target->email)->toBeNull()
        ->and($target->password)->toBe($oldHash);
});

it('resets a legacy user password and clears the legacy flag', function (): void {
    $target = User::factory()->legacySha1()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put("/users/{$target->id}", [
            'username' => $target->username,
            'name' => $target->name,
            'role' => UserRole::User->value,
            'password' => 'passwordbaru',
            'password_confirmation' => 'passwordbaru',
        ])
        ->assertRedirect('/users');

    $target->refresh();
    expect($target->legacy_sha1)->toBeFalse()
        ->and(Hash::check('passwordbaru', $target->password))->toBeTrue();
});

it('prevents admins from editing or deleting super admins', function (): void {
    $boss = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get("/users/{$boss->id}/edit")->assertForbidden();
    $this->actingAs($admin)->put("/users/{$boss->id}", [
        'username' => $boss->username, 'name' => 'x', 'role' => UserRole::User->value,
    ])->assertForbidden();
    $this->actingAs($admin)->delete("/users/{$boss->id}")->assertForbidden();

    expect($boss->fresh())->not->toBeNull();
});

it('lets managers delete other users', function (UserRole $role): void {
    $target = User::factory()->create();

    $this->actingAs(User::factory()->create(['role' => $role]))
        ->delete("/users/{$target->id}")
        ->assertRedirect('/users');

    expect($target->fresh())->toBeNull();
})->with([UserRole::SuperAdmin, UserRole::Admin]);

it('prevents users from deleting themselves', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();

    expect($admin->fresh())->not->toBeNull();
});

it('renders the create and edit forms', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['username' => 'diedit']);

    $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('Tambah user')->assertDontSee('Super Admin</option>', false);
    $this->actingAs($admin)->get("/users/{$target->id}/edit")->assertOk()->assertSee('diedit');
});

it('accepts usernames with dots and rejects spaces', function (string $username, bool $valid): void {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post('/users', [
            'username' => $username,
            'name' => 'Nama',
            'role' => UserRole::User->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('username');
})->with([
    ['budi.priyo', true],
    ['budi_priyo-2', true],
    ['budi priyo', false],
    ["budi'or'1", false],
]);
