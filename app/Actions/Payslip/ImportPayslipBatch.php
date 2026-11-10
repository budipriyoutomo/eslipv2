<?php

declare(strict_types=1);

namespace App\Actions\Payslip;

use App\Enums\PayslipBatchStatus;
use App\Enums\PayslipTemplateType;
use App\Models\Company;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use App\Models\User;
use App\Payslip\Import\PayslipImportFailed;
use App\Payslip\Import\RowError;
use App\Payslip\Import\SpreadsheetRowReader;
use App\Payslip\TemplateResolver;
use App\Payslip\Templates\AbstractPayslipTemplate;
use App\Payslip\Templates\PayslipTemplate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

/**
 * Parse an uploaded Excel file into a draft PayslipBatch with one Payslip per row.
 *
 * All rows are validated first; if any row is invalid nothing is stored and
 * PayslipImportFailed lists every error by Excel row number.
 */
final readonly class ImportPayslipBatch
{
    public function __construct(
        private TemplateResolver $templates,
        private SpreadsheetRowReader $reader,
    ) {}

    /**
     * @throws PayslipImportFailed
     */
    public function handle(
        Company $company,
        PayslipTemplateType $templateType,
        CarbonImmutable $period,
        CarbonImmutable $issuedAt,
        string $path,
        string $originalFilename,
        User $uploader,
    ): PayslipBatch {
        $template = $this->templates->resolve($templateType);
        $rows = $this->readRows($path, $template);

        $errors = [];
        $warnings = [];
        $payslips = [];
        $seenNiks = [];

        foreach ($rows as $rowNumber => $cells) {
            $row = $this->prepare($template->mapRow($cells));
            $nik = is_string($row['nik'] ?? null) ? $row['nik'] : null;

            $validator = Validator::make($row, $template->rules(), [], $this->attributeNames($template));

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = new RowError($rowNumber, $message, $nik);
                }

                continue;
            }

            $attributes = $this->payslipAttributes($template, $row, $cells);

            $computed = $template->computedTakeHomePay(new Payslip($attributes));
            if (! Money::equals($computed, $attributes['take_home_pay'])) {
                $warnings[] = (new RowError($rowNumber, sprintf(
                    'THP di Excel %s berbeda dengan total penerimaan − total potongan %s.',
                    Money::format($attributes['take_home_pay']),
                    Money::format($computed),
                ), $nik))->toString();
            }

            if ($nik !== null && isset($seenNiks[$nik])) {
                $warnings[] = (new RowError($rowNumber, "NIK sama dengan baris {$seenNiks[$nik]}.", $nik))->toString();
            }
            $seenNiks[$nik] ??= $rowNumber;

            $payslips[] = $attributes;
        }

        if ($errors !== []) {
            throw new PayslipImportFailed($errors);
        }

        return DB::transaction(function () use ($company, $templateType, $period, $issuedAt, $originalFilename, $uploader, $payslips, $warnings): PayslipBatch {
            $batch = PayslipBatch::query()->create([
                'company_id' => $company->id,
                'template_type' => $templateType,
                'period' => $period->startOfMonth(),
                'issued_at' => $issuedAt,
                'source_filename' => $originalFilename,
                'uploaded_by' => $uploader->id,
                'status' => PayslipBatchStatus::Draft,
                'total_rows' => count($payslips),
                'warnings' => $warnings === [] ? null : $warnings,
            ]);

            $batch->payslips()->createMany($payslips);

            return $batch;
        });
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $path, PayslipTemplate $template): array
    {
        try {
            $rows = $this->reader->read($path, max(array_keys($template->columnMap())));
        } catch (SpreadsheetException $e) {
            throw PayslipImportFailed::unreadable($e->getMessage());
        }

        if ($rows === []) {
            throw PayslipImportFailed::emptyFile();
        }

        return $rows;
    }

    /**
     * Normalize raw cell values before validation: identity/notes as trimmed strings
     * (numeric NIK without ".0" or exponent), whole-number attendance as int.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function prepare(array $row): array
    {
        $textFields = [...AbstractPayslipTemplate::IDENTITY_FIELDS, ...AbstractPayslipTemplate::NOTE_FIELDS];

        foreach ($row as $field => $value) {
            if (in_array($field, $textFields, true)) {
                $row[$field] = $this->toText($value);
            } elseif (in_array($field, AbstractPayslipTemplate::ATTENDANCE_FIELDS, true)) {
                $row[$field] = $this->toWholeNumber($value);
            } elseif (is_string($value)) {
                $row[$field] = trim($value);
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row  validated row
     * @param  array<int, mixed>  $cells  raw Excel row, kept for audit
     * @return array{nik: string, name: string, position: ?string, email: string, earnings: array<string, string>, deductions: array<string, string>, earning_note: ?string, deduction_note: ?string, attendance: array<string, int>, take_home_pay: string, raw_row: array<int, mixed>}
     */
    private function payslipAttributes(PayslipTemplate $template, array $row, array $cells): array
    {
        $amounts = fn (array $fields): array => array_combine(
            $fields,
            array_map(fn (string $field): string => Money::normalize($this->moneyInput($row[$field] ?? null)), $fields),
        );

        $attendance = [];
        foreach (AbstractPayslipTemplate::ATTENDANCE_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                $attendance[$field] = (int) ($row[$field] ?? 0);
            }
        }

        return [
            'nik' => (string) $row['nik'],
            'name' => (string) $row['name'],
            'position' => isset($row['position']) ? (string) $row['position'] : null,
            'email' => (string) $row['email'],
            'earnings' => $amounts($template->earningFields()),
            'deductions' => $amounts($template->deductionFields()),
            'earning_note' => isset($row['earning_note']) ? (string) $row['earning_note'] : null,
            'deduction_note' => isset($row['deduction_note']) ? (string) $row['deduction_note'] : null,
            'attendance' => $attendance,
            'take_home_pay' => Money::normalize($this->moneyInput($row[AbstractPayslipTemplate::TAKE_HOME_PAY])),
            'raw_row' => $cells,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(PayslipTemplate $template): array
    {
        $names = [];

        foreach ($template->columnMap() as $column => $field) {
            $names[$field] = AbstractPayslipTemplate::label($field)." (kolom {$column})";
        }

        return $names;
    }

    private function toText(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_float($value) && floor($value) === $value => sprintf('%.0F', $value),
            is_scalar($value) => trim((string) $value) === '' ? null : trim((string) $value),
            default => null,
        };
    }

    private function toWholeNumber(mixed $value): mixed
    {
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/\A\s*\d+(\.0+)?\s*\z/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    private function moneyInput(mixed $value): int|float|string|null
    {
        return is_int($value) || is_float($value) || is_string($value) ? $value : null;
    }
}
