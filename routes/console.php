<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Requires the server cron: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1

// Temporary payslip PDFs older than a day (worker crashed before its `finally`).
Schedule::command('eslip:cleanup-temp --hours=24')->dailyAt('02:00')->withoutOverlapping();

// Finished Bus batches and old failed-job rows.
Schedule::command('queue:prune-batches --hours=720 --unfinished=720')->weekly();
Schedule::command('queue:prune-failed --hours=720')->weekly();
