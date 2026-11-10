<x-layouts.app title="User">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-slate-900">User</h1>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('users.index') }}">
                <input type="search" name="q" value="{{ $search }}" placeholder="Cari username / nama"
                       class="rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
            </form>
            @can('create', App\Models\User::class)
                <a href="{{ route('users.create') }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Tambah user</a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $user->username }}</td>
                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->email ?? '–' }}</td>
                        <td class="px-4 py-3">{{ $user->role->label() }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3">
                                @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" class="text-slate-600 hover:text-slate-900">Edit</a>
                                @endcan
                                @can('delete', $user)
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus user '.$user->username.'?') }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada user.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
