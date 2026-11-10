<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayslipBatchStatus;
use App\Models\Company;
use App\Models\PayslipBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayslipBatch>
 */
final class PayslipBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = now()->startOfMonth()->subMonth();

        return [
            'company_id' => Company::factory(),
            'template_type' => fn (array $attributes) => Company::query()->findOrFail($attributes['company_id'])->template_type,
            'period' => $period->toDateString(),
            'issued_at' => $period->copy()->endOfMonth()->toDateString(),
            'source_filename' => 'eslip-'.$period->format('Y-m').'.xls',
            'uploaded_by' => User::factory(),
            'status' => PayslipBatchStatus::Draft,
        ];
    }
}
