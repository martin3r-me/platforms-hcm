<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('hcm_employee_contracts', 'job_activity_alias_id')) {
                $table->foreignId('job_activity_alias_id')->nullable()->after('primary_job_activity_id')
                    ->constrained('hcm_job_activity_aliases')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('hcm_employee_contracts', 'job_activity_alias_id')) {
                $table->dropForeign(['job_activity_alias_id']);
                $table->dropColumn('job_activity_alias_id');
            }
        });
    }
};

