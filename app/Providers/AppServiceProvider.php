<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\LegacyAwareUserProvider;
use App\Support\LegacyPasswordHasher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::provider('legacy-eloquent', function (Application $app, array $config): LegacyAwareUserProvider {
            return new LegacyAwareUserProvider(
                $app->make('hash'),
                $config['model'],
                $app->make(LegacyPasswordHasher::class),
            );
        });
    }
}
