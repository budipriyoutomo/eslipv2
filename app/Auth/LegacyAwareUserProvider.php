<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Support\LegacyPasswordHasher;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Eloquent provider that also accepts legacy sha1 passwords and transparently
 * upgrades them to the configured hasher on successful login.
 */
final class LegacyAwareUserProvider extends EloquentUserProvider
{
    public function __construct(
        Hasher $hasher,
        string $model,
        private readonly LegacyPasswordHasher $legacyHasher,
    ) {
        parent::__construct($hasher, $model);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;

        if (! is_string($plain)) {
            return false;
        }

        if ($user instanceof User && $user->legacy_sha1) {
            return $this->legacyHasher->check($plain, $user->password);
        }

        return parent::validateCredentials($user, $credentials);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        if ($user instanceof User && $user->legacy_sha1) {
            $user->forceFill([
                'password' => $this->hasher->make((string) $credentials['password']),
                'legacy_sha1' => false,
            ])->save();

            return;
        }

        parent::rehashPasswordIfRequired($user, $credentials, $force);
    }
}
