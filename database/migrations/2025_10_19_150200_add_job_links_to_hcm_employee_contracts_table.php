<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->foreignId('job_title_id')->nullable()->after('working_time_model')->constrained('hcm_job_titles')->nullOnDelete();
        });

        Schema::create('hcm_employee_contract_activity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('hcm_employee_contracts')->cascadeOnDelete();
            $table->foreignId('job_activity_id')->constrained('hcm_job_activities')->cascadeOnDelete();
            $table->timestamps();
            // Custom index name to avoid MySQL's 64-char limit
            $table->unique(['contract_id', 'job_activity_id'], 'contract_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_contract_activity_links');
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_title_id');
        });
    }
};


