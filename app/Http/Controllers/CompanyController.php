<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payslip\GeneratePayslipPdf;
use App\Enums\PayslipTemplateType;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Company master data (legacy `tmstbrand`), including mailer, PDF password and logo (DECISIONS D-030).
 */
final class CompanyController extends Controller
{
    private const string LOGO_DIRECTORY = 'logos';

    public function index(): View
    {
        return view('companies.index', [
            'companies' => Company::query()->withCount('payslipBatches')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('companies.form', $this->formData(new Company([
            'template_type' => PayslipTemplateType::Standard,
            'pdf_password_field' => 'nik',
        ])));
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = new Company($request->safe()->except(['logo', 'remove_logo']));
        $company->logo_path = $this->storeLogo($request->file('logo'));
        $company->save();

        return redirect()->route('companies.index')->with('status', "PT {$company->name} berhasil dibuat.");
    }

    public function edit(Company $company): View
    {
        return view('companies.form', $this->formData($company));
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->fill($request->safe()->except(['logo', 'remove_logo']));

        $newLogo = $this->storeLogo($request->file('logo'));
        if ($newLogo !== null || $request->boolean('remove_logo')) {
            $this->deleteLogo($company->logo_path);
            $company->logo_path = $newLogo;
        }

        $company->save();

        return redirect()->route('companies.index')->with('status', "PT {$company->name} berhasil diperbarui.");
    }

    public function destroy(Company $company): RedirectResponse
    {
        if ($company->payslipBatches()->exists()) {
            return back()->with('error', "PT {$company->name} tidak bisa dihapus karena sudah punya batch e-slip.");
        }

        $this->deleteLogo($company->logo_path);
        $company->delete();

        return redirect()->route('companies.index')->with('status', "PT {$company->name} berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Company $company): array
    {
        return [
            'company' => $company,
            'templates' => PayslipTemplateType::cases(),
            'mailers' => CompanyRequest::mailers(),
            'passwordFields' => [
                'nik' => 'NIK karyawan',
                'email' => 'Email karyawan',
                'none' => 'Tanpa password',
            ],
        ];
    }

    private function storeLogo(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $path = $file->store(self::LOGO_DIRECTORY, GeneratePayslipPdf::DISK);

        return $path === false ? null : $path;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(GeneratePayslipPdf::DISK)->delete($path);
        }
    }
}
