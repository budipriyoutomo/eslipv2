<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Only super admins and admins manage users. Admins cannot touch super admins,
 * and nobody can delete their own account.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isManager($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isManager($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->isManager($actor) && $this->outranksOrEquals($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->role->canDelete()
            && $this->isManager($actor)
            && ! $actor->is($target)
            && $this->outranksOrEquals($actor, $target);
    }

    /**
     * Roles the actor may assign to a user.
     *
     * @return list<UserRole>
     */
    public static function assignableRoles(User $actor): array
    {
        return match ($actor->role) {
            UserRole::SuperAdmin => UserRole::cases(),
            UserRole::Admin => [UserRole::Admin, UserRole::User],
            UserRole::User => [],
        };
    }

    private function isManager(User $actor): bool
    {
        return $actor->hasRole(UserRole::SuperAdmin, UserRole::Admin);
    }

    private function outranksOrEquals(User $actor, User $target): bool
    {
        return $actor->role === UserRole::SuperAdmin || $target->role !== UserRole::SuperAdmin;
    }
}
