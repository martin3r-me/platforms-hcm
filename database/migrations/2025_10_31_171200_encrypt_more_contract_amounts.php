<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Umstellung auf TEXT für verschlüsselte Werte
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `above_tariff_amount` TEXT NULL');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `minimum_wage_hourly_rate` TEXT NULL');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `vacation_allowance_amount` TEXT NULL');

        // Hash-Spalten hinzufügen
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD COLUMN `above_tariff_amount_hash` VARCHAR(64) NULL AFTER `above_tariff_amount`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD COLUMN `minimum_wage_hourly_rate_hash` VARCHAR(64) NULL AFTER `minimum_wage_hourly_rate`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD COLUMN `vacation_allowance_amount_hash` VARCHAR(64) NULL AFTER `vacation_allowance_amount`');

        // Indexe
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD INDEX `idx_above_tariff_amount_hash` (`above_tariff_amount_hash`)');
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD INDEX `idx_minimum_wage_hourly_rate_hash` (`minimum_wage_hourly_rate_hash`)');
        DB::statement('ALTER TABLE `hcm_employee_contracts` ADD INDEX `idx_vacation_allowance_amount_hash` (`vacation_allowance_amount_hash`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP INDEX `idx_vacation_allowance_amount_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP INDEX `idx_minimum_wage_hourly_rate_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP INDEX `idx_above_tariff_amount_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP COLUMN `vacation_allowance_amount_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP COLUMN `minimum_wage_hourly_rate_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` DROP COLUMN `above_tariff_amount_hash`');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `vacation_allowance_amount` DECIMAL(10, 2) NULL');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `minimum_wage_hourly_rate` DECIMAL(8, 2) NULL');
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `above_tariff_amount` DECIMAL(12, 2) NULL');
    }
};


