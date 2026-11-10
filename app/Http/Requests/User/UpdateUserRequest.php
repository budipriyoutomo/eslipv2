<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Rules\UniqueUsername;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $targetId = $target instanceof User ? $target->id : null;

        return [
            'username' => ['required', 'string', 'regex:/\A[A-Za-z0-9._-]+\z/', 'max:50', new UniqueUsername($targetId)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)->only($this->assignableRoles())],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return list<UserRole>
     */
    private function assignableRoles(): array
    {
        $actor = $this->user();

        return $actor instanceof User ? UserPolicy::assignableRoles($actor) : [];
    }
}
