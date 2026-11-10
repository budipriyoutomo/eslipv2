<?php

declare(strict_types=1);

namespace App\Payslip\Templates;

use App\Enums\PayslipTemplateType;

/**
 * Legacy `Tmsteslip` controller (has service charge, target and safety-shoes penalty).
 */
final class StandardTemplate extends AbstractPayslipTemplate
{
    public function type(): PayslipTemplateType
    {
        return PayslipTemplateType::Standard;
    }

    public function columnMap(): array
    {
        return [
            1 => 'nik',                  // nik
            2 => 'name',                 // nama
            3 => 'position',             // jabatan
            // 4 => periode (taken from the upload form)
            5 => 'base_salary',          // gaji
            6 => 'fixed_service',        // svctetap
            7 => 'variable_service',     // svcvariabel
            8 => 'target',               // target
            9 => 'overtime',             // lembur
            10 => 'meal_allowance',      // uangmakan
            11 => 'transport_allowance', // transport
            12 => 'mcu_refund',          // pengmcu
            13 => 'bpjs_jht',            // bpjsnakerjht
            14 => 'bpjs_jp',             // bpjsnakerjp
            15 => 'bpjs_health',         // bpjssehat
            16 => 'mcu_deduction',       // potmcu
            17 => 'safety_shoes_penalty', // pinalty
            18 => 'other_deductions',    // potlain
            19 => 'deduction_note',      // ketpot
            20 => 'other_earnings',      // penlain
            21 => 'earning_note',        // ketpen
            22 => 'sick',                // sakit
            23 => 'absent',              // alpha
            24 => 'sudden_leave',        // cutdak
            25 => 'present',             // hadir
            26 => 'take_home_pay',       // thp
            // 27 => penerbitgaji, 28 => namapt (taken from the upload form)
            29 => 'email',               // email
        ];
    }

    public function earningFields(): array
    {
        return [
            'base_salary',
            'fixed_service',
            'variable_service',
            'target',
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
            'safety_shoes_penalty',
            'other_deductions',
        ];
    }
}
