<x-layouts.app title="Dashboard">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-slate-900">Batch e-slip</h1>
        <a href="{{ route('payslip-batches.create') }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Upload e-slip</a>
    </div>

    <form method="GET" action="{{ route('dashboard') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="company_id" class="mb-1 block text-xs font-medium text-slate-500">PT</label>
            <select id="company_id" name="company_id" class="rounded-md border border-slate-300 px-3 py-1.5">
                <option value="">Semua PT</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected($filters['company_id'] === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
            <select id="status" name="status" class="rounded-md border border-slate-300 px-3 py-1.5">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">PT</th>
                    <th class="px-4 py-3">Periode</th>
                    <th class="px-4 py-3">Template</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Terkirim</th>
                    <th class="px-4 py-3 text-right">Gagal</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Diupload</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($batches as $batch)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <a href="{{ route('payslip-batches.show', $batch) }}" class="hover:underline">{{ $batch->company->name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $batch->period->translatedFormat('F Y') }}</td>
                        <td class="px-4 py-3">{{ $batch->template_type->label() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $batch->total_rows }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-green-700">{{ $batch->sent_count }}</td>
                        <td @class(['px-4 py-3 text-right tabular-nums', 'font-medium text-red-700' => $batch->failed_count > 0])>{{ $batch->failed_count }}</td>
                        <td class="px-4 py-3">{{ $batch->status->label() }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $batch->created_at?->translatedFormat('d M Y H:i') }}
                            @if ($batch->uploader) · {{ $batch->uploader->name }} @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">Belum ada batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $batches->links() }}</div>
</x-layouts.app>
