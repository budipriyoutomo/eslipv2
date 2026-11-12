<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\UniqueUsername;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create a login account from the CLI, e.g. the first super admin on a fresh install (Docker trial)
 * where the factory-based seeder is unavailable (Faker is a dev dependency).
 */
#[Signature('eslip:create-user
    {username : Login username}
    {--name= : Display name (default: the username)}
    {--email= : Optional email}
    {--role=super_admin : super_admin, admin or user}
    {--password= : Password; asked interactively when omitted}')]
#[Description('Create a user account')]
final class CreateUser extends Command
{
    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = $this->option('password');

        if (! is_string($password) || $password === '') {
            $password = (string) $this->secret('Password (min. 8 karakter)');
        }

        $data = [
            'username' => $username,
            'name' => is_string($this->option('name')) && $this->option('name') !== '' ? $this->option('name') : $username,
            'email' => is_string($this->option('email')) && $this->option('email') !== '' ? $this->option('email') : null,
            'role' => (string) $this->option('role'),
            'password' => $password,
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'regex:/\A[A-Za-z0-9._-]+\z/', 'max:50', new UniqueUsername],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            ...$data,
            'password' => Hash::make($password),
            'legacy_sha1' => false,
        ]);

        $this->components->info("User {$user->username} ({$user->role->label()}) berhasil dibuat.");

        return self::SUCCESS;
    }
}
