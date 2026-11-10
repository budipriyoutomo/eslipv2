<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\DeliveryLog;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryLog>
 */
final class DeliveryLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payslip_id' => Payslip::factory(),
            'status' => DeliveryStatus::Sent,
            'recipient' => fake()->safeEmail(),
            'error_message' => null,
            'context' => null,
            'attempted_at' => now(),
        ];
    }

    public function failed(string $message = 'SMTP connection refused'): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
