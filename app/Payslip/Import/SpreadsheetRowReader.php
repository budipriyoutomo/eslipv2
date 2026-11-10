<?php

declare(strict_types=1);

namespace App\Payslip\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads payslip rows from the first sheet of an .xls / .xlsx file, like the legacy Excel_reader loop:
 * start at row 1 and stop at the first row whose first column (NIK) is empty.
 *
 * Difference from legacy (DECISIONS D-027): a first row whose column 1 reads "NIK" is treated as a header.
 */
final class SpreadsheetRowReader
{
    /**
     * @return array<int, array<int, mixed>> Excel row number => [1-based column => value]
     */
    public function read(string $path, int $lastColumn): array
    {
        // Only real Excel formats; never fall back to CSV/HTML readers for arbitrary content.
        $reader = IOFactory::createReaderForFile($path, [IOFactory::READER_XLS, IOFactory::READER_XLSX]);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($path);

        try {
            return $this->rows($spreadsheet->getSheet(0), $lastColumn);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function rows(Worksheet $sheet, int $lastColumn): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $lastLetter = Coordinate::stringFromColumnIndex($lastColumn);

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            /** @var list<mixed> $values */
            $values = $sheet->rangeToArray(
                "A{$rowNumber}:{$lastLetter}{$rowNumber}",
                nullValue: null,
                calculateFormulas: true,
                formatData: false,
                returnCellRef: false,
            )[0];

            $cells = [];
            foreach ($values as $index => $value) {
                $cells[$index + 1] = is_string($value) && trim($value) === '' ? null : $value;
            }

            if ($cells[1] === null) {
                break;
            }

            if ($rowNumber === 1 && $this->isHeader($cells[1])) {
                continue;
            }

            $rows[$rowNumber] = $cells;
        }

        return $rows;
    }

    private function isHeader(mixed $firstCell): bool
    {
        return is_string($firstCell) && strtolower(trim($firstCell)) === 'nik';
    }
}
