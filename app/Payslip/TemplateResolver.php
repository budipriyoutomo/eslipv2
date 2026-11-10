<?php

declare(strict_types=1);

namespace App\Payslip;

use App\Enums\PayslipTemplateType;
use App\Payslip\Exceptions\UnsupportedTemplateException;
use App\Payslip\Templates\PayslipTemplate;
use App\Payslip\Templates\StandardTemplate;
use App\Payslip\Templates\XftTemplate;

final class TemplateResolver
{
    /**
     * ND and LLAO are not implemented yet: no legacy source or sample available (DECISIONS D-002).
     *
     * @var array<string, class-string<PayslipTemplate>>
     */
    private const array TEMPLATES = [
        PayslipTemplateType::Standard->value => StandardTemplate::class,
        PayslipTemplateType::Xft->value => XftTemplate::class,
    ];

    public function resolve(PayslipTemplateType $type): PayslipTemplate
    {
        $class = self::TEMPLATES[$type->value] ?? throw UnsupportedTemplateException::for($type);

        return app($class);
    }

    public function supports(PayslipTemplateType $type): bool
    {
        return isset(self::TEMPLATES[$type->value]);
    }

    /**
     * @return list<PayslipTemplateType>
     */
    public function supported(): array
    {
        return array_values(array_filter(PayslipTemplateType::cases(), $this->supports(...)));
    }
}
