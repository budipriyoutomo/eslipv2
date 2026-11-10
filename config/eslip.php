<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payslip mail throttle
    |--------------------------------------------------------------------------
    |
    | Maximum payslip emails per minute, per mailer (SMTP account). Keeps the
    | sender under the hosting provider's SMTP limit.
    |
    */

    'mail_per_minute' => (int) env('ESLIP_MAIL_PER_MINUTE', 20),

    /*
    |--------------------------------------------------------------------------
    | Recipient override
    |--------------------------------------------------------------------------
    |
    | When set, every payslip email goes to this address instead of the
    | employee. Use it for the parallel run (phase 9) and on staging.
    |
    */

    'mail_override_to' => env('ESLIP_MAIL_OVERRIDE_TO'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => env('ESLIP_QUEUE', 'payslips'),

];
