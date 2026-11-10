<?php

declare(strict_types=1);

namespace App\Models;

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
 */
#[Fillable([
    'company_id', 'template_type', 'period', 'issued_at', 'source_filename',
    'uploaded_by', 'status', 'total_rows', 'sent_count', 'failed_count',
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
        ];
    }
}
