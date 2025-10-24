<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->foreignId('health_insurance_company_id')
                ->nullable()
                ->after('id')
                ->constrained('hcm_health_insurance_companies')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropForeign(['health_insurance_company_id']);
            $table->dropColumn('health_insurance_company_id');
        });
    }
};
