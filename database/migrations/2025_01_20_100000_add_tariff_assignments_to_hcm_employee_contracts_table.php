<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prüfe ob Tabelle existiert (wird möglicherweise später erstellt)
        if (!Schema::hasTable('hcm_employee_contracts')) {
            return;
        }
        
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            // Tarifzuordnung
            $table->foreignId('tariff_group_id')->nullable();
            if (Schema::hasTable('hcm_tariff_groups')) {
                $table->foreign('tariff_group_id')
                    ->references('id')
                    ->on('hcm_tariff_groups')
                    ->nullOnDelete();
            }
            
            $table->foreignId('tariff_level_id')->nullable();
            if (Schema::hasTable('hcm_tariff_levels')) {
                $table->foreign('tariff_level_id')
                    ->references('id')
                    ->on('hcm_tariff_levels')
                    ->nullOnDelete();
            }
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
        // Prüfe ob Tabelle existiert
        if (!Schema::hasTable('hcm_employee_contracts')) {
            return;
        }
        
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
