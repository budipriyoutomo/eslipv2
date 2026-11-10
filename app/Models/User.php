<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $username
 * @property string $name
 * @property string|null $email
 * @property string $password
 * @property UserRole $role
 * @property bool $legacy_sha1
 */
#[Fillable(['username', 'name', 'email', 'password', 'role', 'legacy_sha1'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return HasMany<PayslipBatch, $this>
     */
    public function uploadedBatches(): HasMany
    {
        return $this->hasMany(PayslipBatch::class, 'uploaded_by');
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'legacy_sha1' => 'boolean',
        ];
    }
}
