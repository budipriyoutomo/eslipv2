<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
final class PayslipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Field names shared by the Standard and XFT templates.
        $earnings = [
            'base_salary' => '5000000.00',
            'overtime' => '250000.00',
            'meal_allowance' => '400000.00',
            'transport_allowance' => '300000.00',
            'mcu_refund' => '0.00',
            'other_earnings' => '0.00',
        ];
        $deductions = [
            'bpjs_jht' => '100000.00',
            'bpjs_jp' => '50000.00',
            'bpjs_health' => '50000.00',
            'mcu_deduction' => '0.00',
            'other_deductions' => '0.00',
        ];

        return [
            'payslip_batch_id' => PayslipBatch::factory(),
            'nik' => fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'earnings' => $earnings,
            'deductions' => $deductions,
            'earning_note' => null,
            'deduction_note' => null,
            'attendance' => ['sick' => 0, 'absent' => 0, 'sudden_leave' => 0, 'present' => 22],
            'take_home_pay' => '5750000.00',
            'raw_row' => [],
            'delivery_status' => DeliveryStatus::Pending,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'delivery_status' => DeliveryStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['delivery_status' => DeliveryStatus::Failed]);
    }
}
