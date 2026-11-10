<?php

declare(strict_types=1);

namespace App\Payslip\Templates;

use App\Enums\PayslipTemplateType;

/**
 * Legacy `Tmsteslipxft` controller (has bonus, no service charge / target / penalty).
 */
final class XftTemplate extends AbstractPayslipTemplate
{
    public function type(): PayslipTemplateType
    {
        return PayslipTemplateType::Xft;
    }

    public function columnMap(): array
    {
        return [
            1 => 'nik',                  // nik
            2 => 'name',                 // nama
            3 => 'position',             // jabatan
            // 4 => periode (taken from the upload form)
            5 => 'base_salary',          // gaji
            6 => 'bonus',                // bonus
            7 => 'overtime',             // lembur
            8 => 'meal_allowance',       // uangmakan
            9 => 'transport_allowance',  // transport
            10 => 'mcu_refund',          // pengmcu
            11 => 'bpjs_jht',            // bpjsnakerjht
            12 => 'bpjs_jp',             // bpjsnakerjp
            13 => 'bpjs_health',         // bpjssehat
            14 => 'mcu_deduction',       // potmcu
            15 => 'other_deductions',    // potlain
            16 => 'deduction_note',      // ketpot
            17 => 'other_earnings',      // penlain
            18 => 'earning_note',        // ketpen
            19 => 'sick',                // sakit
            20 => 'absent',              // alpha
            21 => 'sudden_leave',        // cutdak
            22 => 'present',             // hadir
            23 => 'take_home_pay',       // thp
            // 24 => penerbitgaji, 25 => namapt (taken from the upload form)
            26 => 'email',               // email
        ];
    }

    public function earningFields(): array
    {
        return [
            'base_salary',
            'bonus',
            'overtime',
            'meal_allowance',
            'transport_allowance',
            'mcu_refund',
            'other_earnings',
        ];
    }

    public function deductionFields(): array
    {
        return [
            'bpjs_jht',
            'bpjs_jp',
            'bpjs_health',
            'mcu_deduction',
            'other_deductions',
        ];
    }
}
