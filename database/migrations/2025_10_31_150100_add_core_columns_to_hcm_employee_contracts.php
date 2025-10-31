<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            // Arbeitszeit
            $table->decimal('work_days_per_week', 4, 2)->nullable()->after('is_active');
            $table->decimal('hours_per_month', 8, 2)->nullable()->change();

            // Vergütung
            $table->string('wage_base_type', 50)->nullable()->after('hours_per_month'); // Lohngrundart
            $table->decimal('hourly_wage', 10, 2)->nullable()->after('wage_base_type');
            $table->decimal('base_salary', 12, 2)->nullable()->after('hourly_wage');

            // Urlaub
            $table->decimal('vacation_entitlement', 5, 2)->nullable()->after('base_salary');
            $table->decimal('vacation_prev_year', 5, 2)->nullable()->after('vacation_entitlement');
            $table->decimal('vacation_taken', 5, 2)->nullable()->after('vacation_prev_year');
            $table->date('vacation_expiry_date')->nullable()->after('vacation_taken');
            $table->boolean('vacation_allowance_enabled')->default(false)->after('vacation_expiry_date');
            $table->decimal('vacation_allowance_amount', 10, 2)->nullable()->after('vacation_allowance_enabled');

            // Kostenstelle-Referenz (optional)
            if (!Schema::hasColumn('hcm_employee_contracts', 'cost_center_id')) {
                $table->unsignedBigInteger('cost_center_id')->nullable()->after('team_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'work_days_per_week',
                'wage_base_type',
                'hourly_wage',
                'base_salary',
                'vacation_entitlement',
                'vacation_prev_year',
                'vacation_taken',
                'vacation_expiry_date',
                'vacation_allowance_enabled',
                'vacation_allowance_amount',
                'cost_center_id',
            ]);
        });
    }
};


