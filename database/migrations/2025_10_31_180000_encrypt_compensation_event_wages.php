<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ändere Spalten zu TEXT für Verschlüsselung
        DB::statement('ALTER TABLE `hcm_contract_compensation_events` MODIFY `hourly_wage` TEXT NULL');
        DB::statement('ALTER TABLE `hcm_contract_compensation_events` MODIFY `base_salary` TEXT NULL');
        
        // Hash-Spalten für Suche
        Schema::table('hcm_contract_compensation_events', function ($table) {
            $table->string('hourly_wage_hash', 64)->nullable()->after('hourly_wage');
            $table->string('base_salary_hash', 64)->nullable()->after('base_salary');
            
            $table->index('hourly_wage_hash');
            $table->index('base_salary_hash');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_contract_compensation_events', function ($table) {
            $table->dropIndex(['hourly_wage_hash']);
            $table->dropIndex(['base_salary_hash']);
            $table->dropColumn(['hourly_wage_hash', 'base_salary_hash']);
        });
        
        DB::statement('ALTER TABLE `hcm_contract_compensation_events` MODIFY `hourly_wage` DECIMAL(10, 2) NULL');
        DB::statement('ALTER TABLE `hcm_contract_compensation_events` MODIFY `base_salary` DECIMAL(12, 2) NULL');
    }
};

