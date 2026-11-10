<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';

    /**
     * Map the legacy CodeIgniter `user.role` value to the new enum.
     */
    public static function fromLegacy(string $legacy): self
    {
        return match ($legacy) {
            'SuperAdmin' => self::SuperAdmin,
            'Admin' => self::Admin,
            default => self::User,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }

    /**
     * Legacy rule: every role except `User` may delete records.
     */
    public function canDelete(): bool
    {
        return $this !== self::User;
    }
}
