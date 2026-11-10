<?php

declare(strict_types=1);

namespace App\Payslip\Exceptions;

use App\Enums\PayslipTemplateType;
use RuntimeException;

final class UnsupportedTemplateException extends RuntimeException
{
    public static function for(PayslipTemplateType $type): self
    {
        return new self("Template [{$type->label()}] belum didukung.");
    }
}
