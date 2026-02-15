<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Umstellung auf TEXT für verschlüsselte Werte
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `hourly_wage` TEXT NULL');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `base_salary` TEXT NULL');
        
        // Hash-Spalten hinzufügen
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD COLUMN `hourly_wage_hash` VARCHAR(64) NULL AFTER `hourly_wage`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD COLUMN `base_salary_hash` VARCHAR(64) NULL AFTER `base_salary`');
        
        // Indexe für Hash-Spalten
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD INDEX `idx_hourly_wage_hash` (`hourly_wage_hash`)');
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD INDEX `idx_base_salary_hash` (`base_salary_hash`)');
    }

    public function down(): void
    {
        try { DB::statement('ALTER TABLE `hcm_employee_contracts` DROP INDEX `idx_base_salary_hash`'); } catch (\Exception $e) {}
        try { DB::statement('ALTER TABLE `hcm_employee_contracts` DROP INDEX `idx_hourly_wage_hash`'); } catch (\Exception $e) {}

        if (Schema::hasColumn('hcm_employee_contracts', 'base_salary_hash')) {
            DB::statement('ALTER TABLE `hcm_employee_contracts` DROP COLUMN `base_salary_hash`');
        }
        if (Schema::hasColumn('hcm_employee_contracts', 'hourly_wage_hash')) {
            DB::statement('ALTER TABLE `hcm_employee_contracts` DROP COLUMN `hourly_wage_hash`');
        }
        if (Schema::hasColumn('hcm_employee_contracts', 'base_salary')) {
            DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `base_salary` DECIMAL(12, 2) NULL');
        }
        if (Schema::hasColumn('hcm_employee_contracts', 'hourly_wage')) {
            DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `hourly_wage` DECIMAL(10, 2) NULL');
        }
    }
};

