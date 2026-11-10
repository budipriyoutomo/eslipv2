<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayslipTemplateType;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $logo_path
 * @property PayslipTemplateType $template_type Default template; a batch may override it.
 * @property string $mailer Mailer name in config/mail.php.
 * @property string $from_address
 * @property string $from_name
 * @property string $pdf_password_field
 */
#[Fillable([
    'code', 'name', 'address', 'city', 'phone', 'logo_path',
    'template_type', 'mailer', 'from_address', 'from_name', 'pdf_password_field',
])]
final class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return HasMany<PayslipBatch, $this>
     */
    public function payslipBatches(): HasMany
    {
        return $this->hasMany(PayslipBatch::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_type' => PayslipTemplateType::class,
        ];
    }
}
