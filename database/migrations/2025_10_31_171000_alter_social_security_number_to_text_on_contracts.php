<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Wechsel auf TEXT, damit verschlüsselte Werte sicher passen
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `social_security_number` TEXT NULL');
    }

    public function down(): void
    {
        // Rückbau auf VARCHAR(32) (ursprünglicher Zustand)
        DB::statement("ALTER TABLE `hcm_employee_contracts` MODIFY `social_security_number` VARCHAR(32) NULL");
    }
};


