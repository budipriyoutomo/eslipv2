<?php

declare(strict_types=1);

namespace App\Payslip\Exceptions;

use DomainException;

final class CannotDispatchBatch extends DomainException
{
    public static function alreadyDispatched(): self
    {
        return new self('Batch ini sudah pernah dikirim.');
    }

    public static function nothingToSend(): self
    {
        return new self('Tidak ada slip yang perlu dikirim.');
    }
}
