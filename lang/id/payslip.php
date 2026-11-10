<?php

declare(strict_types=1);

/*
 * Payslip labels, copied from the legacy FPDF layout (Tmsteslip / Tmsteslipxft).
 */
return [
    'earnings' => 'PENERIMAAN',
    'deductions' => 'POTONGAN',
    'total_earnings' => 'TOTAL PENERIMAAN',
    'total_deductions' => 'TOTAL POTONGAN',
    'take_home_pay' => 'TAKE HOME PAY',
    'period' => 'Periode',

    'fields' => [
        // Identity
        'nik' => 'NIK',
        'name' => 'Nama',
        'position' => 'Jabatan',
        'email' => 'Email',

        // Earnings
        'base_salary' => 'GAJI',
        'bonus' => 'BONUS',
        'fixed_service' => 'SERVICE TETAP',
        'variable_service' => 'SERVICE VARIABEL',
        'target' => 'TARGET',
        'overtime' => 'LEMBUR',
        'meal_allowance' => 'UANG MAKAN',
        'transport_allowance' => 'TRANSPORT',
        'mcu_refund' => 'PENGEMBALIAN MCU',
        'other_earnings' => 'PENERIMAAN LAINNYA',
        'earning_note' => 'KET PENERIMAAN',

        // Deductions
        'bpjs_jht' => 'BPJS KETENAGAKERJAAN JHT',
        'bpjs_jp' => 'BPJS KETENAGAKERJAAN JP',
        'bpjs_health' => 'BPJS KESEHATAN',
        'mcu_deduction' => 'POTONGAN MCU',
        'safety_shoes_penalty' => 'PINALTY SAFETY SHOES',
        'other_deductions' => 'POTONGAN LAIN',
        'deduction_note' => 'KET POTONGAN',

        // Attendance
        'sick' => 'JUMLAH SAKIT',
        'absent' => 'JUMLAH ALPHA',
        'sudden_leave' => 'JUMLAH CUTI DADAKAN',
        'present' => 'JUMLAH KEHADIRAN',

        'take_home_pay' => 'TAKE HOME PAY',
    ],
];
