<?php

declare(strict_types=1);

use App\Actions\Payslip\GeneratePayslipPdf;
use App\Enums\PayslipTemplateType;
use App\Models\Company;
use App\Models\Payslip;
use App\Models\PayslipBatch;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * @param  array<string, mixed>  $companyAttributes
 */
function standardPayslip(array $companyAttributes = []): Payslip
{
    $company = Company::factory()->template(PayslipTemplateType::Standard)->create([
        'name' => 'PT Sumber Rasa',
        'address' => 'Jl. Asia Afrika 1',
        'city' => 'Bandung',
        'phone' => '022-1234567',
        ...$companyAttributes,
    ]);

    $batch = PayslipBatch::factory()->for($company)->create([
        'period' => '2026-10-01',
        'issued_at' => '2026-10-31',
    ]);

    return Payslip::factory()->for($batch, 'batch')->create([
        'nik' => '1001',
        'name' => 'Andi Wijaya',
        'position' => 'Cook',
        'email' => 'andi@example.com',
        'earnings' => [
            'base_salary' => '5000000.00', 'fixed_service' => '750000.00', 'variable_service' => '250000.50',
            'target' => '100000.00', 'overtime' => '120000.00', 'meal_allowance' => '400000.00',
            'transport_allowance' => '300000.00', 'mcu_refund' => '50000.00', 'other_earnings' => '20000.00',
        ],
        'deductions' => [
            'bpjs_jht' => '100000.00', 'bpjs_jp' => '50000.00', 'bpjs_health' => '40000.00',
            'mcu_deduction' => '25000.00', 'safety_shoes_penalty' => '150000.00', 'other_deductions' => '10000.00',
        ],
        'earning_note' => 'Insentif Oktober',
        'deduction_note' => 'Kasbon',
        'attendance' => ['sick' => 1, 'absent' => 0, 'sudden_leave' => 2, 'present' => 23],
        'take_home_pay' => '6615000.50',
    ]);
}

function pdfText(string $binary): string
{
    return (new Parser)->parseContent($binary)->getText();
}

it('renders a valid A5 landscape PDF', function (): void {
    $binary = app(GeneratePayslipPdf::class)->handle(standardPayslip(), protect: false);

    expect($binary)->toStartWith('%PDF-');

    $pages = (new Parser)->parseContent($binary)->getPages();
    expect($pages)->toHaveCount(1);

    [$x0, $y0, $width, $height] = array_map(floatval(...), $pages[0]->getDetails()['MediaBox']);
    // A5 = 210 x 148 mm = 595.28 x 419.53 pt; landscape means width > height.
    expect(round($width))->toBe(595.0)->and(round($height))->toBe(420.0);
});

it('contains the header, identity and all standard lines', function (): void {
    $text = pdfText(app(GeneratePayslipPdf::class)->handle(standardPayslip(), protect: false));

    expect($text)->toContain(
        'PT Sumber Rasa', 'Jl. Asia Afrika 1', '022-1234567',
        'NIK : 1001', 'Nama : Andi Wijaya', 'Jabatan : Cook', 'Periode : Oktober 2026',
        'PENERIMAAN', 'POTONGAN',
        'GAJI', 'SERVICE TETAP', 'SERVICE VARIABEL', 'TARGET', 'LEMBUR', 'UANG MAKAN', 'TRANSPORT',
        'PENGEMBALIAN MCU', 'PENERIMAAN LAINNYA', 'KET PENERIMAAN :', 'Insentif Oktober',
        'BPJS KETENAGAKERJAAN JHT', 'BPJS KETENAGAKERJAAN JP', 'BPJS KESEHATAN', 'POTONGAN MCU',
        'PINALTY SAFETY SHOES', 'POTONGAN LAIN', 'KET POTONGAN :', 'Kasbon',
        'JUMLAH SAKIT', 'JUMLAH ALPHA', 'JUMLAH CUTI DADAKAN', 'JUMLAH KEHADIRAN',
        'Bandung, 31 Oktober 2026',
    );
});

it('prints amounts in legacy format with matching totals', function (): void {
    $text = pdfText(app(GeneratePayslipPdf::class)->handle(standardPayslip(), protect: false));

    expect($text)->toContain(
        '= 5.000.000,00',          // gaji
        '= 250.000,50',            // service variabel
        '= 150.000,00',            // pinalty
        '= 6.990.000,50',          // total penerimaan
        '= 375.000,00',            // total potongan
        '= 6.615.000,50',          // take home pay
    )->and($text)->toMatch('/TOTAL PENERIMAAN\s*= 6\.990\.000,50/')
        ->and($text)->toMatch('/TOTAL POTONGAN\s*= 375\.000,00/')
        ->and($text)->toMatch('/TAKE HOME PAY\s*= 6\.615\.000,50/');
});

it('renders xft lines without the standard-only ones', function (): void {
    $company = Company::factory()->template(PayslipTemplateType::Xft)->create();
    $batch = PayslipBatch::factory()->for($company)->create();
    $payslip = Payslip::factory()->for($batch, 'batch')->create([
        'earnings' => ['base_salary' => '4500000.00', 'bonus' => '500000.00'],
        'deductions' => ['bpjs_jht' => '90000.00'],
        'take_home_pay' => '4910000.00',
    ]);

    $text = pdfText(app(GeneratePayslipPdf::class)->handle($payslip, protect: false));

    expect($text)->toContain('BONUS', '= 500.000,00', '= 5.000.000,00', '= 90.000,00', '= 4.910.000,00')
        ->and($text)->not->toContain('SERVICE TETAP')
        ->and($text)->not->toContain('PINALTY SAFETY SHOES');
});

it('protects the PDF with the NIK by default using AES-256', function (): void {
    $payslip = standardPayslip();
    $generate = app(GeneratePayslipPdf::class);

    $binary = $generate->handle($payslip);

    expect($generate->password($payslip))->toBe('1001')
        ->and($binary)->toContain('/Encrypt')
        ->and($binary)->toContain('/AESV3')
        ->and($generate->handle($payslip, protect: false))->not->toContain('/Encrypt');
});

it('uses the configured password field per company', function (string $field, ?string $expected): void {
    $payslip = standardPayslip(['pdf_password_field' => $field]);

    expect(app(GeneratePayslipPdf::class)->password($payslip))->toBe($expected);
})->with([
    ['nik', '1001'],
    ['email', 'andi@example.com'],
    ['none', null],
]);

it('does not encrypt when the company disables the password', function (): void {
    $binary = app(GeneratePayslipPdf::class)->handle(standardPayslip(['pdf_password_field' => 'none']));

    expect($binary)->not->toContain('/Encrypt');
});

it('rejects an unknown password field', function (): void {
    app(GeneratePayslipPdf::class)->password(standardPayslip(['pdf_password_field' => 'tanggal_lahir']));
})->throws(InvalidArgumentException::class);

it('omits the city when the company has none', function (): void {
    $text = pdfText(app(GeneratePayslipPdf::class)->handle(standardPayslip(['city' => null]), protect: false));

    expect($text)->toContain('31 Oktober 2026')->and($text)->not->toContain(', 31 Oktober 2026');
});

it('stores the protected PDF on the private disk', function (): void {
    Storage::fake('local');
    $payslip = standardPayslip();

    $path = app(GeneratePayslipPdf::class)->store($payslip);

    expect($path)->toBe("payslips/tmp/{$payslip->payslip_batch_id}/1001-{$payslip->id}.pdf");
    Storage::disk('local')->assertExists($path);
    expect(Storage::disk('local')->get($path))->toContain('/Encrypt');
});

it('does not embed the TCPDF branding link', function (): void {
    $text = pdfText(app(GeneratePayslipPdf::class)->handle(standardPayslip(), protect: false));

    expect($text)->not->toContain('TCPDF');
});
