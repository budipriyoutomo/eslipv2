<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
        };
    }
}
