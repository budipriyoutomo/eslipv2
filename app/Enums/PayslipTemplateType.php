<?php

declare(strict_types=1);

namespace App\Enums;

enum PayslipTemplateType: string
{
    case Standard = 'standard';
    case Nd = 'nd';
    case Llao = 'llao';
    case Xft = 'xft';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Nd => 'ND',
            self::Llao => 'LLAO',
            self::Xft => 'XFT',
        };
    }
}
