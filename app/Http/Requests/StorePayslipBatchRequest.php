<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PayslipTemplateType;
use App\Models\Company;
use App\Payslip\TemplateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StorePayslipBatchRequest extends FormRequest
{
    public const int MAX_FILE_KILOBYTES = 10240;

    public function authorize(): bool
    {
        // Legacy: every role (SuperAdmin, Admin, User) may upload payslips.
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            // Empty = use the company's default template (DECISIONS D-004).
            'template_type' => ['nullable', Rule::enum(PayslipTemplateType::class)],
            'period' => ['required', 'date_format:Y-m'],
            'issued_at' => ['required', 'date_format:Y-m-d'],
            'file' => ['required', 'file', 'extensions:xls,xlsx', 'max:'.self::MAX_FILE_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_id' => 'PT',
            'template_type' => 'template',
            'period' => 'periode gaji',
            'issued_at' => 'tanggal terbit',
            'file' => 'file Excel',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['company_id', 'template_type'])) {
                    return;
                }

                if (! app(TemplateResolver::class)->supports($this->templateType())) {
                    $validator->errors()->add('template_type', "Template {$this->templateType()->label()} belum didukung.");
                }
            },
        ];
    }

    public function company(): Company
    {
        return Company::query()->findOrFail($this->integer('company_id'));
    }

    public function templateType(): PayslipTemplateType
    {
        return $this->enum('template_type', PayslipTemplateType::class) ?? $this->company()->template_type;
    }

    public function period(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->string('period')->toString())->startOfMonth();
    }

    public function issuedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('issued_at')->toString());
    }
}
