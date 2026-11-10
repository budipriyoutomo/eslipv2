<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:super_admin,admin')
 */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = array_map(UserRole::from(...), $roles);

        abort_unless($user instanceof User && $user->hasRole(...$allowed), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
