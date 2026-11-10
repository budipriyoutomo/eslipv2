<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_batch_id')->constrained()->cascadeOnDelete();
            $table->string('nik', 30);
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('email');
            $table->json('earnings');
            $table->json('deductions');
            $table->text('earning_note')->nullable();
            $table->text('deduction_note')->nullable();
            $table->json('attendance')->nullable();
            $table->decimal('take_home_pay', 15, 2);
            $table->json('raw_row');
            $table->string('delivery_status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['payslip_batch_id', 'delivery_status']);
            $table->index('nik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
