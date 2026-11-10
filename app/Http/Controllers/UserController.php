<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('q')->trim()->toString();

        $users = User::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $q): Builder => $q
                    ->whereLike('username', '%'.$search.'%')
                    ->orWhereLike('name', '%'.$search.'%'),
            ))
            ->orderBy('username')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'search' => $search]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', [
            'user' => new User,
            'roles' => UserPolicy::assignableRoles($this->actor($request)),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        /** @var array{username: string, name: string, email: ?string, role: string, password: string} $data */
        $data = $request->validated();

        User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
            'legacy_sha1' => false,
        ]);

        return redirect()->route('users.index')->with('status', 'User berhasil dibuat.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', [
            'user' => $user,
            'roles' => UserPolicy::assignableRoles($this->actor($request)),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['password', 'password_confirmation']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->string('password')->toString());
            $data['legacy_sha1'] = false;
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User berhasil dihapus.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
