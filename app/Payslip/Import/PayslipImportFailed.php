<?php

declare(strict_types=1);

namespace App\Payslip\Import;

use RuntimeException;

/**
 * Raised when an uploaded file has invalid rows. Nothing is persisted in that case.
 */
final class PayslipImportFailed extends RuntimeException
{
    /**
     * @param  list<RowError>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(count($errors).' baris tidak valid.');
    }

    public static function emptyFile(): self
    {
        return new self([new RowError(null, 'File tidak berisi data: kolom 1 (NIK) pada baris pertama kosong.')]);
    }

    public static function unreadable(string $reason): self
    {
        return new self([new RowError(null, 'File tidak bisa dibaca: '.$reason)]);
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(fn (RowError $error): string => $error->toString(), $this->errors);
    }
}
