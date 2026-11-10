<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PayslipTemplateType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Local development data only. Production data comes from `eslip:import-legacy`.
     */
    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'username' => 'superadmin',
            'name' => 'Super Admin',
        ]);

        Company::factory()->template(PayslipTemplateType::Standard)->create([
            'code' => 'DEV-STD',
            'name' => 'PT Contoh Standard',
            'mailer' => 'log',
        ]);

        Company::factory()->template(PayslipTemplateType::Xft)->create([
            'code' => 'DEV-XFT',
            'name' => 'PT Contoh XFT',
            'mailer' => 'log',
        ]);
    }
}
