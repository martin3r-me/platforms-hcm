<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP INDEX `idx_base_salary_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP INDEX `idx_hourly_wage_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP COLUMN `base_salary_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP COLUMN `hourly_wage_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `base_salary` DECIMAL(12, 2) NULL');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `hourly_wage` DECIMAL(10, 2) NULL');
    }
};

