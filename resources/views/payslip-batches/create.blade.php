<x-layouts.app title="Upload e-slip">
    <h1 class="mb-6 text-xl font-semibold text-slate-900">Upload e-slip</h1>

    @if (session('import_errors'))
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-medium">File tidak diimport. Perbaiki baris berikut lalu upload ulang:</p>
            <ul class="mt-2 max-h-72 list-disc space-y-1 overflow-y-auto pl-5">
                @foreach (session('import_errors') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($companies->isEmpty())
        <p class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Belum ada data PT. Jalankan import data lama (<code>php artisan eslip:import-legacy</code>) terlebih dahulu.
        </p>
    @else
        <form method="POST" action="{{ route('payslip-batches.store') }}" enctype="multipart/form-data"
              class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6">
            @csrf

            <div>
                <label for="company_id" class="mb-1 block text-sm font-medium">PT</label>
                <select id="company_id" name="company_id" required class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                    <option value="">Pilih PT</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>
                            {{ $company->name }} (default: {{ $company->template_type->label() }})
                        </option>
                    @endforeach
                </select>
                @error('company_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="template_type" class="mb-1 block text-sm font-medium">Template</label>
                <select id="template_type" name="template_type" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                    <option value="">Ikuti default PT</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->value }}" @selected(old('template_type') === $template->value)>{{ $template->label() }}</option>
                    @endforeach
                </select>
                @error('template_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="period" class="mb-1 block text-sm font-medium">Periode gaji</label>
                    <input id="period" name="period" type="month" value="{{ old('period') }}" required
                           class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                    @error('period') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="issued_at" class="mb-1 block text-sm font-medium">Tanggal terbit</label>
                    <input id="issued_at" name="issued_at" type="date" value="{{ old('issued_at') }}" required
                           class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                    @error('issued_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="file" class="mb-1 block text-sm font-medium">File Excel (.xls / .xlsx, maks. 10 MB)</label>
                <input id="file" name="file" type="file" accept=".xls,.xlsx" required class="block w-full text-sm">
                <p class="mt-1 text-xs text-slate-500">Sheet pertama dibaca mulai baris 1 dan berhenti di baris dengan kolom NIK kosong. Baris judul "NIK" di baris 1 dilewati.</p>
                @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Upload & preview</button>
            </div>
        </form>
    @endif
</x-layouts.app>
