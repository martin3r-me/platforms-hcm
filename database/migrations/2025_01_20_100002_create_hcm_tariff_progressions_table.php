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
            
            // Foreign Keys nur erstellen, wenn die Tabellen existieren
            $table->foreignId('employee_contract_id');
            if (Schema::hasTable('hcm_employee_contracts')) {
                $table->foreign('employee_contract_id')
                    ->references('id')
                    ->on('hcm_employee_contracts')
                    ->cascadeOnDelete();
            }
            
            $table->foreignId('from_tariff_level_id')->nullable();
            if (Schema::hasTable('hcm_tariff_levels')) {
                $table->foreign('from_tariff_level_id')
                    ->references('id')
                    ->on('hcm_tariff_levels')
                    ->nullOnDelete();
            }
            
            $table->foreignId('to_tariff_level_id');
            if (Schema::hasTable('hcm_tariff_levels')) {
                $table->foreign('to_tariff_level_id')
                    ->references('id')
                    ->on('hcm_tariff_levels')
                    ->cascadeOnDelete();
            }
            $table->date('progression_date');
            $table->enum('progression_reason', ['automatic', 'manual', 'promotion', 'adjustment'])->default('automatic');
            $table->text('progression_notes')->nullable();
            $table->timestamps();
            
            $table->index(['employee_contract_id', 'progression_date'], 'tariff_progressions_contract_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_tariff_progressions');
    }
};
