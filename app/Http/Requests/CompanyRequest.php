<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Payslip\GeneratePayslipPdf;
use App\Enums\PayslipTemplateType;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update a company (legacy `tmstbrand`). Route middleware limits this to super admins and admins.
 */
final class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/\A[A-Za-z0-9._-]+\z/',
                Rule::unique('companies', 'code')->ignore($company instanceof Company ? $company->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'template_type' => ['required', Rule::enum(PayslipTemplateType::class)],
            'mailer' => ['required', Rule::in(self::mailers())],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'pdf_password_field' => ['required', Rule::in(GeneratePayslipPdf::PASSWORD_FIELDS)],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama PT',
            'address' => 'alamat',
            'city' => 'kota',
            'phone' => 'telepon',
            'template_type' => 'template',
            'mailer' => 'akun email',
            'from_address' => 'email pengirim',
            'from_name' => 'nama pengirim',
            'pdf_password_field' => 'password PDF',
        ];
    }

    /**
     * Mailers defined in config/mail.php that can send on their own.
     *
     * @return list<string>
     */
    public static function mailers(): array
    {
        /** @var array<string, array<string, mixed>> $mailers */
        $mailers = config('mail.mailers', []);

        return array_values(array_filter(
            array_keys($mailers),
            fn (string $name): bool => ! in_array($mailers[$name]['transport'] ?? null, ['failover', 'roundrobin'], true),
        ));
    }
}
