<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\PayslipBatchStatus;
use App\Enums\PayslipTemplateType;
use Carbon\CarbonImmutable;
use Database\Factories\PayslipBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property PayslipTemplateType $template_type
 * @property CarbonImmutable $period First day of the payroll month.
 * @property CarbonImmutable $issued_at
 * @property string $source_filename
 * @property int|null $uploaded_by
 * @property PayslipBatchStatus $status
 * @property int $total_rows
 * @property int $sent_count
 * @property int $failed_count
 * @property list<string>|null $warnings
 * @property string|null $job_batch_id
 */
#[Fillable([
    'company_id', 'template_type', 'period', 'issued_at', 'source_filename',
    'uploaded_by', 'status', 'total_rows', 'sent_count', 'failed_count', 'warnings', 'job_batch_id',
])]
final class PayslipBatch extends Model
{
    /** @use HasFactory<PayslipBatchFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'total_rows' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Recompute sent/failed counters from the payslips (idempotent, safe under retries and resends)
     * and mark the batch done once nothing is pending any more.
     */
    public function refreshDeliveryCounts(): void
    {
        $counts = $this->payslips()
            ->toBase()
            ->selectRaw('delivery_status, count(*) as aggregate')
            ->groupBy('delivery_status')
            ->pluck('aggregate', 'delivery_status');

        $this->sent_count = (int) ($counts[DeliveryStatus::Sent->value] ?? 0);
        $this->failed_count = (int) ($counts[DeliveryStatus::Failed->value] ?? 0);

        if ($this->status === PayslipBatchStatus::Processing && (int) ($counts[DeliveryStatus::Pending->value] ?? 0) === 0) {
            $this->status = PayslipBatchStatus::Done;
        }

        $this->save();
    }

    public function pendingCount(): int
    {
        return max(0, $this->total_rows - $this->sent_count - $this->failed_count);
    }

    public function progressPercent(): int
    {
        return $this->total_rows === 0 ? 0 : (int) floor(($this->sent_count + $this->failed_count) * 100 / $this->total_rows);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_type' => PayslipTemplateType::class,
            'status' => PayslipBatchStatus::class,
            'period' => 'immutable_date',
            'issued_at' => 'immutable_date',
            'total_rows' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'warnings' => 'array',
        ];
    }
}
