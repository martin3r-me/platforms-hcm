<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->foreignId('insurance_status_id')->nullable()->after('working_time_model')
                ->constrained('hcm_insurance_statuses')->nullOnDelete();
            $table->foreignId('pension_type_id')->nullable()->after('insurance_status_id')
                ->constrained('hcm_pension_types')->nullOnDelete();
            $table->foreignId('employment_relationship_id')->nullable()->after('pension_type_id')
                ->constrained('hcm_employment_relationships')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropForeign(['insurance_status_id']);
            $table->dropForeign(['pension_type_id']);
            $table->dropForeign(['employment_relationship_id']);
            $table->dropColumn(['insurance_status_id','pension_type_id','employment_relationship_id']);
        });
    }
};


