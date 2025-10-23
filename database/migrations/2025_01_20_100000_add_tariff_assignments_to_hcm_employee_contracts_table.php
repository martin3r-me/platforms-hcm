<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            // Tarifzuordnung
            $table->foreignId('tariff_group_id')->nullable()->constrained('hcm_tariff_groups')->nullOnDelete();
            $table->foreignId('tariff_level_id')->nullable()->constrained('hcm_tariff_levels')->nullOnDelete();
            $table->date('tariff_assignment_date')->nullable(); // Datum der Tarifzuordnung
            
            // Stufenprogression
            $table->date('tariff_level_start_date')->nullable(); // Startdatum der aktuellen Stufe
            $table->date('next_tariff_level_date')->nullable(); // Berechnetes Datum für nächste Stufe
            
            $table->index(['tariff_group_id', 'tariff_level_id']);
            $table->index(['tariff_assignment_date']);
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropForeign(['tariff_group_id']);
            $table->dropForeign(['tariff_level_id']);
            $table->dropColumn([
                'tariff_group_id',
                'tariff_level_id', 
                'tariff_assignment_date',
                'tariff_level_start_date',
                'next_tariff_level_date'
            ]);
        });
    }
};
