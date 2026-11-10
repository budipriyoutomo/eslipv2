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
        $earnings = [
            'gaji' => '5000000.00',
            'lembur' => '250000.00',
            'uangmakan' => '400000.00',
            'transport' => '300000.00',
            'pengmcu' => '0.00',
            'penlain' => '0.00',
        ];
        $deductions = [
            'bpjsnakerjht' => '100000.00',
            'bpjsnakerjp' => '50000.00',
            'bpjssehat' => '50000.00',
            'potmcu' => '0.00',
            'potlain' => '0.00',
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
