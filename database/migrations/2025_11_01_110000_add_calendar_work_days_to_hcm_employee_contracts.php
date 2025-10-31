<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->string('calendar_work_days', 50)->nullable()->after('work_days_per_week')
                ->comment('Kalendarische Arbeitstage, z.B. "Mo, Di, Mi, Do, Fr"');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropColumn('calendar_work_days');
        });
    }
};

