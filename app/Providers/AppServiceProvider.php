<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\LegacyAwareUserProvider;
use App\Jobs\SendPayslipJob;
use App\Support\LegacyPasswordHasher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        // One bucket per SMTP account so a big batch never trips the provider's sending limit.
        RateLimiter::for('payslip-mail', fn (SendPayslipJob $job): Limit => Limit::perMinute((int) config('eslip.mail_per_minute'))
            ->by('payslip-mail:'.$job->mailerName()));
    }
}
