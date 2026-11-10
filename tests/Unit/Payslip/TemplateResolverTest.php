<?php

declare(strict_types=1);

use App\Enums\PayslipTemplateType;
use App\Payslip\Exceptions\UnsupportedTemplateException;
use App\Payslip\TemplateResolver;
use App\Payslip\Templates\AbstractPayslipTemplate;
use App\Payslip\Templates\StandardTemplate;
use App\Payslip\Templates\XftTemplate;

it('resolves implemented templates', function (PayslipTemplateType $type, string $class): void {
    $template = (new TemplateResolver)->resolve($type);

    expect($template)->toBeInstanceOf($class)
        ->and($template->type())->toBe($type);
})->with([
    [PayslipTemplateType::Standard, StandardTemplate::class],
    [PayslipTemplateType::Xft, XftTemplate::class],
]);

it('refuses templates without a verified legacy mapping', function (PayslipTemplateType $type): void {
    (new TemplateResolver)->resolve($type);
})->with([PayslipTemplateType::Nd, PayslipTemplateType::Llao])
    ->throws(UnsupportedTemplateException::class);

it('lists the supported templates', function (): void {
    expect((new TemplateResolver)->supported())->toBe([PayslipTemplateType::Standard, PayslipTemplateType::Xft]);
});

it('has an Indonesian label for every field of every template', function (PayslipTemplateType $type): void {
    $template = (new TemplateResolver)->resolve($type);

    foreach ($template->columnMap() as $field) {
        expect(AbstractPayslipTemplate::label($field))->not->toStartWith('payslip.fields.');
    }
})->with(fn (): array => (new TemplateResolver)->supported());

it('keeps earning and deduction fields inside the column map', function (PayslipTemplateType $type): void {
    $template = (new TemplateResolver)->resolve($type);
    $mapped = array_values($template->columnMap());

    expect($mapped)->toContain(...$template->earningFields(), ...$template->deductionFields())
        ->and(array_intersect($template->earningFields(), $template->deductionFields()))->toBeEmpty();
})->with(fn (): array => (new TemplateResolver)->supported());
