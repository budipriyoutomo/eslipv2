<?php

declare(strict_types=1);

namespace App\Payslip\Import;

final readonly class RowError
{
    /**
     * @param  int|null  $row  Excel row number; null for file-level errors.
     */
    public function __construct(
        public ?int $row,
        public string $message,
        public ?string $nik = null,
    ) {}

    public function toString(): string
    {
        if ($this->row === null) {
            return $this->message;
        }

        $who = $this->nik !== null ? " (NIK {$this->nik})" : '';

        return "Baris {$this->row}{$who}: {$this->message}";
    }
}
