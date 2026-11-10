<?php

declare(strict_types=1);

namespace App\Enums;

enum PayslipBatchStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Processing => 'Diproses',
            self::Done => 'Selesai',
        };
    }
}
