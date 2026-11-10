<?php

declare(strict_types=1);

use App\Enums\PayslipTemplateType;
use App\Models\Company;
use App\Models\PayslipBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function companyPayload(array $overrides = []): array
{
    return [
        'code' => 'BJ',
        'name' => 'PT Boga Jabar',
        'address' => 'Jl. Merdeka 1',
        'city' => 'Bandung',
        'phone' => '022-555',
        'template_type' => 'standard',
        'mailer' => 'smtp_bogajabar',
        'from_address' => 'hrd@bogajabar.test',
        'from_name' => 'HRD Boga Jabar',
        'pdf_password_field' => 'nik',
        ...$overrides,
    ];
}

it('is only for super admins and admins', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/companies')->assertForbidden();
    $this->actingAs($user)->post('/companies', companyPayload())->assertForbidden();
    $this->actingAs($user)->delete("/companies/{$company->id}")->assertForbidden();
});

it('lists companies with their settings', function (): void {
    Company::factory()->create(['name' => 'PT Maju', 'mailer' => 'smtp_gmail', 'template_type' => PayslipTemplateType::Xft]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/companies')
        ->assertOk()
        ->assertSee('PT Maju')
        ->assertSee('smtp_gmail')
        ->assertSee('XFT');
});

it('creates a company with a logo on the private disk', function (): void {
    Storage::fake('local');

    $this->actingAs(User::factory()->admin()->create())
        ->post('/companies', companyPayload(['logo' => UploadedFile::fake()->image('logo.png', 200, 80)]))
        ->assertRedirect('/companies')
        ->assertSessionHas('status', 'PT PT Boga Jabar berhasil dibuat.');

    $company = Company::query()->sole();
    expect($company->code)->toBe('BJ')
        ->and($company->template_type)->toBe(PayslipTemplateType::Standard)
        ->and($company->mailer)->toBe('smtp_bogajabar')
        ->and($company->logo_path)->toStartWith('logos/');
    Storage::disk('local')->assertExists($company->logo_path);
});

it('validates company input', function (array $overrides, string $field): void {
    Company::factory()->create(['code' => 'ADA']);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/companies', companyPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate code' => [['code' => 'ADA'], 'code'],
    'code with spaces' => [['code' => 'P T'], 'code'],
    'missing name' => [['name' => ''], 'name'],
    'unknown template' => [['template_type' => 'payroll'], 'template_type'],
    'undefined mailer' => [['mailer' => 'smtp_tidak_ada'], 'mailer'],
    'failover mailer' => [['mailer' => 'failover'], 'mailer'],
    'bad from address' => [['from_address' => 'bukan-email'], 'from_address'],
    'unknown password field' => [['pdf_password_field' => 'tanggal_lahir'], 'pdf_password_field'],
    'logo not an image' => [['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')], 'logo'],
]);

it('updates a company and replaces its logo', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('logos/old.png', 'old');
    $company = Company::factory()->create(['code' => 'BJ', 'logo_path' => 'logos/old.png']);

    $this->actingAs(User::factory()->admin()->create())
        ->put("/companies/{$company->id}", companyPayload([
            'name' => 'PT Boga Jabar Baru',
            'pdf_password_field' => 'none',
            'logo' => UploadedFile::fake()->image('new.png'),
        ]))
        ->assertRedirect('/companies');

    $company->refresh();
    expect($company->name)->toBe('PT Boga Jabar Baru')
        ->and($company->pdf_password_field)->toBe('none')
        ->and($company->logo_path)->not->toBe('logos/old.png');
    Storage::disk('local')->assertMissing('logos/old.png');
    Storage::disk('local')->assertExists($company->logo_path);
});

it('keeps the logo when none is uploaded and removes it on request', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('logos/keep.png', 'x');
    $company = Company::factory()->create(['code' => 'BJ', 'logo_path' => 'logos/keep.png']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put("/companies/{$company->id}", companyPayload());
    expect($company->fresh()->logo_path)->toBe('logos/keep.png');

    $this->actingAs($admin)->put("/companies/{$company->id}", companyPayload(['remove_logo' => '1']));
    expect($company->fresh()->logo_path)->toBeNull();
    Storage::disk('local')->assertMissing('logos/keep.png');
});

it('deletes a company without batches', function (): void {
    $company = Company::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete("/companies/{$company->id}")
        ->assertRedirect('/companies');

    expect($company->fresh())->toBeNull();
});

it('refuses to delete a company that has batches', function (): void {
    $batch = PayslipBatch::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from('/companies')
        ->delete("/companies/{$batch->company_id}")
        ->assertRedirect('/companies')
        ->assertSessionHas('error');

    expect($batch->company->fresh())->not->toBeNull();
});

it('renders the create and edit forms with configured mailers', function (): void {
    $admin = User::factory()->admin()->create();
    $company = Company::factory()->create(['name' => 'PT Edit Saya']);

    $this->actingAs($admin)->get('/companies/create')
        ->assertOk()
        ->assertSee('<option value="smtp_bogajabar"', false)
        ->assertDontSee('<option value="failover"', false);

    $this->actingAs($admin)->get("/companies/{$company->id}/edit")
        ->assertOk()
        ->assertSee('PT Edit Saya');
});
