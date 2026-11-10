<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Enums\PayslipBatchStatus;
use App\Enums\PayslipTemplateType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\DeliveryLog;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

it('creates every model through its factory', function (string $model): void {
    /** @var class-string<Model> $model */
    $instance = $model::factory()->create();

    expect($instance->exists)->toBeTrue();
})->with([
    User::class,
    Company::class,
    PayslipBatch::class,
    Payslip::class,
    DeliveryLog::class,
]);

it('casts user attributes', function (): void {
    $user = User::factory()->admin()->create()->fresh();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->legacy_sha1)->toBeFalse()
        ->and($user->toArray())->not->toHaveKey('password');
});

it('stores a legacy sha1 password verbatim', function (): void {
    $user = User::factory()->legacySha1('rahasia')->create()->fresh();

    expect($user->password)->toBe(sha1('rahasia'))
        ->and($user->legacy_sha1)->toBeTrue();
});

it('casts company template type', function (): void {
    $company = Company::factory()->template(PayslipTemplateType::Xft)->create()->fresh();

    expect($company->template_type)->toBe(PayslipTemplateType::Xft)
        ->and($company->pdf_password_field)->toBe('nik');
});

it('defaults the batch template to the company template', function (): void {
    $company = Company::factory()->template(PayslipTemplateType::Xft)->create();

    $batch = PayslipBatch::factory()->for($company)->create()->fresh();

    expect($batch->template_type)->toBe(PayslipTemplateType::Xft)
        ->and($batch->status)->toBe(PayslipBatchStatus::Draft)
        ->and($batch->period)->toBeInstanceOf(CarbonImmutable::class)
        ->and($batch->period->day)->toBe(1)
        ->and($batch->total_rows)->toBe(0);
});

it('lets a batch override the company template', function (): void {
    $company = Company::factory()->template(PayslipTemplateType::Standard)->create();

    $batch = PayslipBatch::factory()->for($company)->create(['template_type' => PayslipTemplateType::Xft]);

    expect($batch->fresh()->template_type)->toBe(PayslipTemplateType::Xft);
});

it('casts payslip json and decimal columns', function (): void {
    $payslip = Payslip::factory()->create([
        'earnings' => ['base_salary' => '1234567.89'],
        'take_home_pay' => 1234567.891,
    ])->fresh();

    expect($payslip->earnings)->toBe(['base_salary' => '1234567.89'])
        ->and($payslip->take_home_pay)->toBe('1234567.89')
        ->and($payslip->delivery_status)->toBe(DeliveryStatus::Pending)
        ->and($payslip->attendance)->toHaveKeys(['sick', 'absent', 'sudden_leave', 'present']);
});

it('looks up amounts in earnings then deductions', function (): void {
    $payslip = Payslip::factory()->make([
        'earnings' => ['base_salary' => '100.00'],
        'deductions' => ['other_deductions' => '25.00'],
    ]);

    expect($payslip->amount('base_salary'))->toBe('100.00')
        ->and($payslip->amount('other_deductions'))->toBe('25.00')
        ->and($payslip->amount('bonus'))->toBe('0.00');
});

it('allows delivery logs without a payslip for legacy history', function (): void {
    $log = DeliveryLog::factory()->create([
        'payslip_id' => null,
        'context' => ['source' => 'tstatus', 'nik' => '123'],
    ])->fresh();

    expect($log->payslip_id)->toBeNull()
        ->and($log->context)->toBe(['source' => 'tstatus', 'nik' => '123']);
});
