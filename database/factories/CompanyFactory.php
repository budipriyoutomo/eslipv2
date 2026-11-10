<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayslipTemplateType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PT##??'),
            'name' => 'PT '.fake()->company(),
            'address' => fake()->streetAddress(),
            'city' => 'Bandung',
            'phone' => fake()->numerify('022-#######'),
            'logo_path' => null,
            'template_type' => PayslipTemplateType::Standard,
            'mailer' => 'smtp_bogajabar',
            'from_address' => fake()->companyEmail(),
            'from_name' => 'HRD',
            'pdf_password_field' => 'nik',
        ];
    }

    public function template(PayslipTemplateType $type): static
    {
        return $this->state(fn (): array => ['template_type' => $type]);
    }
}
