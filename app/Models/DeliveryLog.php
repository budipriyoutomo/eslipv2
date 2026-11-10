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
