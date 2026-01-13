<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hcm_contract_vacation_days', function (Blueprint $table) {
            // Optional: Rohwerte aus externen Systemen (z.B. Nostradamus wageComponent kommt in Stunden)
            $table->decimal('vacation_hours', 8, 2)->nullable()->after('type');
            $table->decimal('vacation_days', 6, 3)->nullable()->after('vacation_hours');
        });

        Schema::table('hcm_contract_absence_days', function (Blueprint $table) {
            // Optional: Rohwerte aus externen Systemen (z.B. sickness_hours / sickness_days)
            $table->decimal('absence_hours', 8, 2)->nullable()->after('type');
            $table->decimal('absence_days', 6, 3)->nullable()->after('absence_hours');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_contract_vacation_days', function (Blueprint $table) {
            $table->dropColumn(['vacation_hours', 'vacation_days']);
        });

        Schema::table('hcm_contract_absence_days', function (Blueprint $table) {
            $table->dropColumn(['absence_hours', 'absence_days']);
        });
    }
};

