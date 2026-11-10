<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_logs', function (Blueprint $table): void {
            $table->id();
            // Nullable: legacy `tstatus` history rows have no matching payslip (see DECISIONS D-016).
            $table->foreignId('payslip_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('recipient')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['payslip_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_logs');
    }
};
