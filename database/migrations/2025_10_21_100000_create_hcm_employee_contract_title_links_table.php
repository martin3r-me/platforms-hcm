<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove direct foreign key from contracts table
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('hcm_employee_contracts', 'job_title_id')) {
                // Check if foreign key exists before dropping
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_NAME = 'hcm_employee_contracts' 
                    AND COLUMN_NAME = 'job_title_id' 
                    AND CONSTRAINT_NAME != 'PRIMARY'
                ");
                
                if (!empty($foreignKeys)) {
                    $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
                }
                $table->dropColumn('job_title_id');
            }
        });

        // Create pivot table for contract-title relationships
        if (!Schema::hasTable('hcm_employee_contract_title_links')) {
            Schema::create('hcm_employee_contract_title_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('hcm_employee_contracts')->cascadeOnDelete();
                $table->foreignId('job_title_id')->constrained('hcm_job_titles')->cascadeOnDelete();
                $table->timestamps();
                // Custom index name to avoid MySQL's 64-char limit
                $table->unique(['contract_id', 'job_title_id'], 'contract_title_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_contract_title_links');
        
        // Restore direct foreign key
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('hcm_employee_contracts', 'job_title_id')) {
                $table->foreignId('job_title_id')->nullable()->after('working_time_model')->constrained('hcm_job_titles')->nullOnDelete();
            }
        });
    }
};
