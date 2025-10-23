<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            // Übertarifliche Bezahlung
            $table->boolean('is_above_tariff')->default(false)->after('next_tariff_level_date');
            $table->decimal('above_tariff_amount', 10, 2)->nullable()->after('is_above_tariff');
            $table->text('above_tariff_reason')->nullable()->after('above_tariff_amount');
            $table->date('above_tariff_start_date')->nullable()->after('above_tariff_reason');
            
            // Mindestlohn / Pseudo-Tarif
            $table->boolean('is_minimum_wage')->default(false)->after('above_tariff_start_date');
            $table->decimal('minimum_wage_hourly_rate', 8, 2)->nullable()->after('is_minimum_wage');
            $table->decimal('minimum_wage_monthly_hours', 6, 2)->nullable()->after('minimum_wage_hourly_rate');
            $table->text('minimum_wage_notes')->nullable()->after('minimum_wage_monthly_hours');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'is_above_tariff',
                'above_tariff_amount',
                'above_tariff_reason',
                'above_tariff_start_date',
                'is_minimum_wage',
                'minimum_wage_hourly_rate',
                'minimum_wage_monthly_hours',
                'minimum_wage_notes'
            ]);
        });
    }
};
