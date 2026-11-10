<x-layouts.app title="Riwayat pengiriman">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900">Riwayat pengiriman</h1>
        @if ($batch)
            <p class="mt-1 text-sm text-slate-500">
                Batch: <a href="{{ route('payslip-batches.show', $batch) }}" class="underline">{{ $batch->company->name }} · {{ $batch->period->translatedFormat('F Y') }}</a>
                · <a href="{{ route('delivery-logs.index') }}" class="underline">tampilkan semua</a>
            </p>
        @endif
    </div>

    <form method="GET" action="{{ route('delivery-logs.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        @if ($batch)
            <input type="hidden" name="batch" value="{{ $batch->id }}">
        @endif
        <div>
            <label for="q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
            <input id="q" type="search" name="q" value="{{ $search }}" placeholder="NIK, nama, atau email"
                   class="rounded-md border border-slate-300 px-3 py-1.5">
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
            <select id="status" name="status" class="rounded-md border border-slate-300 px-3 py-1.5">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">NIK</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">PT · Periode</th>
                    <th class="px-4 py-3">Tujuan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $log->attempted_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="px-4 py-3 font-mono">{{ $log->nik() ?? '–' }}</td>
                        <td class="px-4 py-3">{{ $log->employeeName() ?? '–' }}</td>
                        <td class="px-4 py-3">
                            @if ($log->payslip)
                                <a href="{{ route('payslip-batches.show', $log->payslip->batch) }}" class="hover:underline">{{ $log->companyName() }} · {{ $log->periodLabel() }}</a>
                            @else
                                {{ $log->companyName() ?? '–' }} · {{ $log->periodLabel() ?? '–' }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $log->recipient ?? '–' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded px-2 py-0.5 text-xs font-medium',
                                'bg-green-50 text-green-700' => $log->status === App\Enums\DeliveryStatus::Sent,
                                'bg-red-50 text-red-700' => $log->status === App\Enums\DeliveryStatus::Failed,
                                'bg-slate-100 text-slate-600' => $log->status === App\Enums\DeliveryStatus::Pending,
                            ])>{{ $log->status->label() }}</span>
                            @if ($log->isLegacy())
                                <span class="ml-1 rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-500">sistem lama</span>
                            @endif
                        </td>
                        <td class="max-w-xs truncate px-4 py-3 text-xs text-slate-500" title="{{ $log->error_message }}">{{ $log->error_message }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">Belum ada riwayat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
