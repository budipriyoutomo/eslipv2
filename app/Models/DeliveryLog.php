<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DeliveryLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $payslip_id Null for history imported from legacy `tstatus`.
 * @property DeliveryStatus $status
 * @property string|null $recipient
 * @property string|null $error_message
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable $attempted_at
 */
#[Fillable(['payslip_id', 'status', 'recipient', 'error_message', 'context', 'attempted_at'])]
final class DeliveryLog extends Model
{
    /** @use HasFactory<DeliveryLogFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * True for history rows copied from the legacy `tstatus` table (no payslip, data in `context`).
     * Legacy context keys: source, nik, name, period, company, issued_at (see eslip:import-legacy).
     */
    public function isLegacy(): bool
    {
        return $this->payslip_id === null;
    }

    public function nik(): ?string
    {
        return $this->payslip !== null ? $this->payslip->nik : $this->contextString('nik');
    }

    public function employeeName(): ?string
    {
        return $this->payslip !== null ? $this->payslip->name : $this->contextString('name');
    }

    public function companyName(): ?string
    {
        return $this->payslip !== null ? $this->payslip->batch->company->name : $this->contextString('company');
    }

    public function periodLabel(): ?string
    {
        if ($this->payslip !== null) {
            return $this->payslip->batch->period->translatedFormat('F Y');
        }

        $period = $this->contextString('period');

        return $period === null ? null : rescue(
            fn (): string => CarbonImmutable::parse($period)->translatedFormat('F Y'),
            $period,
            report: false,
        );
    }

    private function contextString(string $key): ?string
    {
        $value = $this->context[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'context' => 'array',
            'attempted_at' => 'immutable_datetime',
        ];
    }
}
