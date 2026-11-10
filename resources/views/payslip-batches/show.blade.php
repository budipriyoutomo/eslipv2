@php($money = fn (string $amount): string => App\Support\Money::format($amount))
<x-layouts.app :title="'Batch #'.$batch->id" :refresh="$batch->status === App\Enums\PayslipBatchStatus::Processing ? 10 : null">
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

    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="text-sm">
                <span class="font-medium text-green-700">{{ $batch->sent_count }} terkirim</span> ·
                <span class="font-medium text-red-700">{{ $batch->failed_count }} gagal</span> ·
                <span class="text-slate-600">{{ $batch->pendingCount() }} menunggu</span>
            </div>
            <div class="flex items-center gap-3">
                @if ($batch->status === App\Enums\PayslipBatchStatus::Draft)
                    <form method="POST" action="{{ route('payslip-batches.send', $batch) }}"
                          onsubmit="return confirm({{ Illuminate\Support\Js::from('Kirim '.$batch->total_rows.' e-slip sekarang?') }})">
                        @csrf
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Kirim semua</button>
                    </form>
                @endif
                @if ($batch->failed_count > 0)
                    <form method="POST" action="{{ route('payslip-batches.resend-failed', $batch) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Kirim ulang yang gagal ({{ $batch->failed_count }})</button>
                    </form>
                @endif
            </div>
        </div>
        @if ($batch->status !== App\Enums\PayslipBatchStatus::Draft)
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100" role="progressbar"
                 aria-valuenow="{{ $batch->progressPercent() }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full bg-green-600" style="width: {{ $batch->progressPercent() }}%"></div>
            </div>
            @if ($batch->status === App\Enums\PayslipBatchStatus::Processing)
                <p class="mt-2 text-xs text-slate-500">Sedang dikirim di background · {{ $batch->progressPercent() }}% · halaman diperbarui otomatis tiap 10 detik.</p>
            @endif
        @endif
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

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('payslip-batches.show', $batch) }}" @class(['rounded-full px-3 py-1', 'bg-slate-900 text-white' => $statusFilter === null, 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50' => $statusFilter !== null])>Semua</a>
            @foreach ($statuses as $option)
                <a href="{{ route('payslip-batches.show', [$batch, 'status' => $option->value]) }}" @class(['rounded-full px-3 py-1', 'bg-slate-900 text-white' => $statusFilter === $option, 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50' => $statusFilter !== $option])>{{ $option->label() }}</a>
            @endforeach
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('delivery-logs.index', ['batch' => $batch->id]) }}" class="text-slate-600 underline hover:text-slate-900">Riwayat pengiriman batch ini</a>
            @if ($canDelete)
                <form method="POST" action="{{ route('payslip-batches.destroy', $batch) }}"
                      onsubmit="return confirm({{ Illuminate\Support\Js::from('Hapus batch draft ini beserta '.$batch->total_rows.' slip?') }})">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus batch</button>
                </form>
            @endif
        </div>
    </div>

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
                    <th class="px-4 py-3"><span class="sr-only">PDF</span></th>
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
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('payslip-batches.payslips.pdf', [$batch, $row['payslip']]) }}" target="_blank" rel="noopener"
                               class="whitespace-nowrap text-slate-600 hover:text-slate-900">Preview PDF</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payslips->links() }}</div>
</x-layouts.app>
