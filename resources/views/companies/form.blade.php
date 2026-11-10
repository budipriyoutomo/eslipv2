@php($editing = $company->exists)
<x-layouts.app :title="$editing ? 'Edit PT' : 'Tambah PT'">
    <h1 class="mb-6 text-xl font-semibold text-slate-900">{{ $editing ? 'Edit PT' : 'Tambah PT' }}</h1>

    <form method="POST" action="{{ $editing ? route('companies.update', $company) : route('companies.store') }}" enctype="multipart/form-data"
          class="max-w-2xl space-y-6 rounded-lg border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold text-slate-900">Header slip</legend>
            @foreach ([
                ['code', 'Kode', true],
                ['name', 'Nama PT', true],
                ['address', 'Alamat', false],
                ['city', 'Kota (tempat terbit)', false],
                ['phone', 'Telepon', false],
            ] as [$field, $label, $required])
                <div @class(['sm:col-span-2' => in_array($field, ['name', 'address'], true)])>
                    <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field, $company->{$field}) }}" @required($required)
                           class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                    @error($field) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <label for="logo" class="mb-1 block text-sm font-medium">Logo (PNG/JPG, maks. 1 MB)</label>
                <input id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg" class="block w-full text-sm">
                @if ($company->logo_path)
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300">
                        Hapus logo saat ini ({{ basename($company->logo_path) }})
                    </label>
                @endif
                @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <fieldset class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold text-slate-900">Pengiriman</legend>

            <div>
                <label for="template_type" class="mb-1 block text-sm font-medium">Template default</label>
                <select id="template_type" name="template_type" required class="w-full rounded-md border border-slate-300 px-3 py-2">
                    @foreach ($templates as $template)
                        <option value="{{ $template->value }}" @selected(old('template_type', $company->template_type?->value) === $template->value)>{{ $template->label() }}</option>
                    @endforeach
                </select>
                @error('template_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pdf_password_field" class="mb-1 block text-sm font-medium">Password PDF</label>
                <select id="pdf_password_field" name="pdf_password_field" required class="w-full rounded-md border border-slate-300 px-3 py-2">
                    @foreach ($passwordFields as $value => $label)
                        <option value="{{ $value }}" @selected(old('pdf_password_field', $company->pdf_password_field) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('pdf_password_field') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="mailer" class="mb-1 block text-sm font-medium">Akun email (mailer)</label>
                <select id="mailer" name="mailer" required class="w-full rounded-md border border-slate-300 px-3 py-2">
                    <option value="">Pilih mailer</option>
                    @foreach ($mailers as $mailer)
                        <option value="{{ $mailer }}" @selected(old('mailer', $company->mailer) === $mailer)>{{ $mailer }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Kredensial SMTP diatur di file <code>.env</code> server.</p>
                @error('mailer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="from_address" class="mb-1 block text-sm font-medium">Email pengirim</label>
                <input id="from_address" name="from_address" type="email" value="{{ old('from_address', $company->from_address) }}" required
                       class="w-full rounded-md border border-slate-300 px-3 py-2">
                @error('from_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="from_name" class="mb-1 block text-sm font-medium">Nama pengirim</label>
                <input id="from_name" name="from_name" type="text" value="{{ old('from_name', $company->from_name) }}" required
                       class="w-full rounded-md border border-slate-300 px-3 py-2">
                @error('from_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('companies.index') }}" class="text-sm text-slate-500 hover:text-slate-900">Batal</a>
        </div>
    </form>
</x-layouts.app>
