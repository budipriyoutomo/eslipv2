<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('template_type', 20);
            $table->date('period');
            $table->date('issued_at');
            $table->string('source_filename');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_batches');
    }
};
