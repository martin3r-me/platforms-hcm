<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_tariff_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_contract_id')->constrained('hcm_employee_contracts')->cascadeOnDelete();
            $table->foreignId('from_tariff_level_id')->nullable()->constrained('hcm_tariff_levels')->nullOnDelete();
            $table->foreignId('to_tariff_level_id')->constrained('hcm_tariff_levels')->cascadeOnDelete();
            $table->date('progression_date');
            $table->enum('progression_reason', ['automatic', 'manual', 'promotion', 'adjustment'])->default('automatic');
            $table->text('progression_notes')->nullable();
            $table->timestamps();
            
            $table->index(['employee_contract_id', 'progression_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_tariff_progressions');
    }
};
