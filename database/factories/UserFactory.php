<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'role' => UserRole::User,
            'legacy_sha1' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::SuperAdmin]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Admin]);
    }

    /**
     * A user migrated from the legacy app, still holding an unsalted sha1 hash.
     */
    public function legacySha1(string $plainPassword = 'password'): static
    {
        return $this->state(fn (): array => [
            'password' => sha1($plainPassword),
            'legacy_sha1' => true,
        ]);
    }
}
