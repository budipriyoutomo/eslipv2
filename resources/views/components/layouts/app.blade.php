@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="font-semibold text-slate-900">{{ config('app.name') }}</a>
                <a href="{{ route('dashboard') }}" @class(['text-sm', 'font-medium text-slate-900' => request()->routeIs('dashboard'), 'text-slate-500 hover:text-slate-900' => ! request()->routeIs('dashboard')])>Dashboard</a>
                <a href="{{ route('payslip-batches.create') }}" @class(['text-sm', 'font-medium text-slate-900' => request()->routeIs('payslip-batches.*'), 'text-slate-500 hover:text-slate-900' => ! request()->routeIs('payslip-batches.*')])>Upload</a>
                @can('viewAny', App\Models\User::class)
                    <a href="{{ route('users.index') }}" @class(['text-sm', 'font-medium text-slate-900' => request()->routeIs('users.*'), 'text-slate-500 hover:text-slate-900' => ! request()->routeIs('users.*')])>User</a>
                @endcan
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-500">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-slate-900">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
