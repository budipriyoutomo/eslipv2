<x-layouts.app title="PT">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-slate-900">PT</h1>
        <a href="{{ route('companies.create') }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Tambah PT</a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kota</th>
                    <th class="px-4 py-3">Template</th>
                    <th class="px-4 py-3">Akun email</th>
                    <th class="px-4 py-3">Password PDF</th>
                    <th class="px-4 py-3 text-right">Batch</th>
                    <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($companies as $company)
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $company->code }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $company->name }}</td>
                        <td class="px-4 py-3">{{ $company->city ?? '–' }}</td>
                        <td class="px-4 py-3">{{ $company->template_type->label() }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $company->mailer }}</td>
                        <td class="px-4 py-3">{{ $company->pdf_password_field === 'none' ? 'Tanpa password' : strtoupper($company->pdf_password_field) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $company->payslip_batches_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('companies.edit', $company) }}" class="text-slate-600 hover:text-slate-900">Edit</a>
                                @if ($company->payslip_batches_count === 0)
                                    <form method="POST" action="{{ route('companies.destroy', $company) }}"
                                          onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus '.$company->name.'?') }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">Belum ada PT.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
