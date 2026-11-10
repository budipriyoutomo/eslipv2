<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Placeholder until the batch dashboard lands in phase 8.
 */
final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
