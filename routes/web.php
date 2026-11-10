<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PayslipBatchController;
use App\Http\Controllers\PayslipBatchDispatchController;
use App\Http\Controllers\PayslipPdfController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::resource('payslip-batches', PayslipBatchController::class)
        ->only(['create', 'store', 'show'])
        ->parameters(['payslip-batches' => 'batch']);

    Route::post('/payslip-batches/{batch}/send', [PayslipBatchDispatchController::class, 'send'])
        ->name('payslip-batches.send');
    Route::post('/payslip-batches/{batch}/resend-failed', [PayslipBatchDispatchController::class, 'resendFailed'])
        ->name('payslip-batches.resend-failed');

    Route::get('/payslip-batches/{batch}/payslips/{payslip}/pdf', PayslipPdfController::class)
        ->scopeBindings()
        ->name('payslip-batches.payslips.pdf');

    Route::middleware('role:super_admin,admin')->group(function (): void {
        Route::resource('users', UserController::class)->except('show');
    });
});
