<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Amounts in `earnings` / `deductions` are decimal strings keyed by template field name
 * (e.g. ['gaji' => '5000000.00']). Never rely on key order — see DECISIONS D-011.
 *
 * @property int $id
 * @property int $payslip_batch_id
 * @property string $nik
 * @property string $name
 * @property string|null $position
 * @property string $email
 * @property array<string, string> $earnings
 * @property array<string, string> $deductions
 * @property string|null $earning_note
 * @property string|null $deduction_note
 * @property array<string, int>|null $attendance
 * @property string $take_home_pay
 * @property array<int|string, mixed> $raw_row
 * @property DeliveryStatus $delivery_status
 * @property CarbonImmutable|null $sent_at
 */
#[Fillable([
    'payslip_batch_id', 'nik', 'name', 'position', 'email',
    'earnings', 'deductions', 'earning_note', 'deduction_note', 'attendance',
    'take_home_pay', 'raw_row', 'delivery_status', 'sent_at',
])]
final class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'delivery_status' => 'pending',
    ];

    /**
     * @return BelongsTo<PayslipBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayslipBatch::class, 'payslip_batch_id');
    }

    /**
     * @return HasMany<DeliveryLog, $this>
     */
    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class);
    }

    /**
     * Amount for a template field, looked up in earnings then deductions. Missing → '0.00'.
     */
    public function amount(string $field): string
    {
        return $this->earnings[$field] ?? $this->deductions[$field] ?? '0.00';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'earnings' => 'array',
            'deductions' => 'array',
            'attendance' => 'array',
            'raw_row' => 'array',
            'take_home_pay' => 'decimal:2',
            'delivery_status' => DeliveryStatus::class,
            'sent_at' => 'immutable_datetime',
        ];
    }
}
