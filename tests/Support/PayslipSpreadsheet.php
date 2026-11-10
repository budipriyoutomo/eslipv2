<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds synthetic payslip Excel files laid out like the legacy templates (1-based columns).
 *
 * Synthetic until anonymized real samples are available (DECISIONS D-027).
 */
final class PayslipSpreadsheet
{
    /**
     * A valid Standard row; THP = total earnings − total deductions.
     *
     * @return array<int, mixed>
     */
    public static function standardRow(string $nik = '1001', string $email = 'andi@example.com'): array
    {
        return [
            1 => $nik, 2 => 'Andi', 3 => 'Cook', 4 => '2026-10-01',
            5 => 5000000, 6 => 750000, 7 => 250000.5, 8 => 100000, 9 => 120000,
            10 => 400000, 11 => 300000, 12 => 50000, 13 => 100000, 14 => 50000,
            15 => 40000, 16 => 25000, 17 => 150000, 18 => 10000, 19 => 'Kasbon',
            20 => 20000, 21 => 'Insentif', 22 => 1, 23 => 0, 24 => 2, 25 => 23,
            26 => 6615000.5, 27 => '2026-10-31', 28 => 'PT Lama', 29 => $email,
        ];
    }

    /**
     * A valid XFT row; THP = total earnings − total deductions.
     *
     * @return array<int, mixed>
     */
    public static function xftRow(string $nik = '2001', string $email = 'budi@example.com'): array
    {
        return [
            1 => $nik, 2 => 'Budi', 3 => 'Barista', 4 => '2026-10-01',
            5 => 4500000, 6 => 500000, 7 => 100000, 8 => 350000, 9 => 250000,
            10 => 0, 11 => 90000, 12 => 45000, 13 => 45000, 14 => 0, 15 => 20000,
            16 => 'Kasbon', 17 => 15000, 18 => 'Insentif', 19 => 0, 20 => 1, 21 => 0, 22 => 24,
            23 => 5515000, 24 => '2026-10-31', 25 => 'PT Lama', 26 => $email,
        ];
    }

    /**
     * Write rows to a temp file. A cell value starting with "=" is written as a formula.
     *
     * @param  list<array<int, mixed>>  $rows
     */
    public static function write(array $rows, string $extension = 'xls'): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $index => $row) {
            foreach ($row as $column => $value) {
                if ($value === null) {
                    continue;
                }

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($column).($index + 1), $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'eslip').'.'.$extension;
        $writer = $extension === 'xlsx' ? new Xlsx($spreadsheet) : new Xls($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     */
    public static function upload(array $rows, string $extension = 'xls', string $name = 'eslip-oktober'): UploadedFile
    {
        return new UploadedFile(self::write($rows, $extension), "{$name}.{$extension}", null, null, true);
    }
}
