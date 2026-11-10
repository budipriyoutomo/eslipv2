@php($editing = $user->exists)
<x-layouts.app :title="$editing ? 'Edit user' : 'Tambah user'">
    <h1 class="mb-6 text-xl font-semibold text-slate-900">{{ $editing ? 'Edit user' : 'Tambah user' }}</h1>

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}"
          class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        @foreach ([
            ['username', 'Username', 'text', 'username'],
            ['name', 'Nama', 'text', 'name'],
            ['email', 'Email (opsional)', 'email', 'email'],
        ] as [$field, $label, $type, $autocomplete])
            <div>
                <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}"
                       value="{{ old($field, $user->{$field}) }}" @required($field !== 'email')
                       class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                @error($field)
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <div>
            <label for="role" class="mb-1 block text-sm font-medium">Role</label>
            <select id="role" name="role" required class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @error('role')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">
                Password @if ($editing)<span class="font-normal text-slate-500">(kosongkan jika tidak diubah)</span>@endif
            </label>
            <input id="password" name="password" type="password" autocomplete="new-password" @required(! $editing)
                   class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Ulangi password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $editing)
                   class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-slate-900">Batal</a>
        </div>
    </form>
</x-layouts.app>
