<?php

declare(strict_types=1);

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the login page to guests', function (): void {
    $this->get('/login')->assertOk()->assertSee('Username');
});

it('redirects guests to login', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('logs in with username and password', function (): void {
    $user = User::factory()->create(['username' => 'budi']);

    $this->post('/login', ['username' => 'budi', 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('logs in a legacy sha1 user and rehashes the password', function (): void {
    $user = User::factory()->legacySha1('rahasia123')->create(['username' => 'legacy']);

    $this->post('/login', ['username' => 'legacy', 'password' => 'rahasia123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->legacy_sha1)->toBeFalse()
        ->and($user->password)->not->toBe(sha1('rahasia123'))
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('keeps logging in after the legacy password was rehashed', function (): void {
    User::factory()->legacySha1('rahasia123')->create(['username' => 'legacy']);

    $this->post('/login', ['username' => 'legacy', 'password' => 'rahasia123']);
    $this->post('/logout');

    $this->post('/login', ['username' => 'legacy', 'password' => 'rahasia123'])
        ->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});

it('rejects a wrong password', function (): void {
    User::factory()->create(['username' => 'budi']);

    $this->from('/login')
        ->post('/login', ['username' => 'budi', 'password' => 'salah'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['username' => 'Username atau password salah.']);

    $this->assertGuest();
});

it('rejects a wrong password for a legacy user without rehashing', function (): void {
    $user = User::factory()->legacySha1('rahasia123')->create(['username' => 'legacy']);

    $this->post('/login', ['username' => 'legacy', 'password' => 'salah'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
    expect($user->refresh()->legacy_sha1)->toBeTrue()
        ->and($user->password)->toBe(sha1('rahasia123'));
});

it('rejects an unknown username', function (): void {
    $this->post('/login', ['username' => 'tidakada', 'password' => 'password'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('rate limits repeated failed logins', function (): void {
    User::factory()->create(['username' => 'budi']);

    foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $_) {
        $this->post('/login', ['username' => 'budi', 'password' => 'salah']);
    }

    $this->post('/login', ['username' => 'budi', 'password' => 'password'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
    expect(session('errors')->first('username'))->toContain('Terlalu banyak percobaan login');
});

it('logs out', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('redirects authenticated users away from login', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/dashboard');
});
