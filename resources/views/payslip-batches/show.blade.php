@php($money = fn (string $amount): string => App\Support\Money::format($amount))
<x-layouts.app :title="'Batch #'.$batch->id">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900">{{ $batch->company->name }} · {{ $batch->period->translatedFormat('F Y') }}</h1>
        <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
            <div><dt class="text-slate-500">Template</dt><dd class="font-medium">{{ $batch->template_type->label() }}</dd></div>
            <div><dt class="text-slate-500">Tanggal terbit</dt><dd class="font-medium">{{ $batch->issued_at->translatedFormat('d F Y') }}</dd></div>
            <div><dt class="text-slate-500">Status</dt><dd class="font-medium">{{ $batch->status->label() }}</dd></div>
            <div><dt class="text-slate-500">Jumlah slip</dt><dd class="font-medium">{{ $batch->total_rows }}</dd></div>
            <div><dt class="text-slate-500">Total THP</dt><dd class="font-medium">{{ $money($totalTakeHomePay) }}</dd></div>
            <div><dt class="text-slate-500">File</dt><dd class="truncate font-medium" title="{{ $batch->source_filename }}">{{ $batch->source_filename }}</dd></div>
            <div><dt class="text-slate-500">Diupload oleh</dt><dd class="font-medium">{{ $batch->uploader?->name ?? '–' }}</dd></div>
        </dl>
    </div>

    @if ($batch->warnings)
        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <p class="font-medium">{{ count($batch->warnings) }} peringatan (tidak memblokir pengiriman):</p>
            <ul class="mt-2 max-h-60 list-disc space-y-1 overflow-y-auto pl-5">
                @foreach ($batch->warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">NIK</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Jabatan</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3 text-right">Penerimaan</th>
                    <th class="px-4 py-3 text-right">Potongan</th>
                    <th class="px-4 py-3 text-right">THP</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $row['payslip']->nik }}</td>
                        <td class="px-4 py-3">{{ $row['payslip']->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $row['payslip']->position ?? '–' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $row['payslip']->email }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $money($row['total_earnings']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $money($row['total_deductions']) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $money($row['payslip']->take_home_pay) }}</td>
                        <td class="px-4 py-3">{{ $row['payslip']->delivery_status->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payslips->links() }}</div>
</x-layouts.app>
