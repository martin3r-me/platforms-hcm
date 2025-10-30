<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->foreignId('primary_job_activity_id')->nullable()->after('employment_relationship_id')
                ->constrained('hcm_job_activities')->nullOnDelete();
            $table->index('primary_job_activity_id');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropIndex(['primary_job_activity_id']);
            $table->dropForeign(['primary_job_activity_id']);
            $table->dropColumn('primary_job_activity_id');
        });
    }
};


