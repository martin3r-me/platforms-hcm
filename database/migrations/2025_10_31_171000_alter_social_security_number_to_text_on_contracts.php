<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wechsel auf TEXT, damit verschlüsselte Werte sicher passen
        DB::statement('ALTER TABLE `hcm_employee_contracts` MODIFY `social_security_number` TEXT NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('hcm_employee_contracts', 'social_security_number')) {
            DB::statement("ALTER TABLE `hcm_employee_contracts` MODIFY `social_security_number` VARCHAR(32) NULL");
        }
    }
};


