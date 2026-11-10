<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('template_type', 20)->default('standard');
            $table->string('mailer', 50);
            $table->string('from_address');
            $table->string('from_name');
            $table->string('pdf_password_field', 50)->default('nik');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
