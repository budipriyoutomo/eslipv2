<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslip_batches', function (Blueprint $table): void {
            // Non-blocking import warnings (e.g. THP mismatch), shown on the preview page.
            $table->json('warnings')->nullable()->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('payslip_batches', function (Blueprint $table): void {
            $table->dropColumn('warnings');
        });
    }
};
