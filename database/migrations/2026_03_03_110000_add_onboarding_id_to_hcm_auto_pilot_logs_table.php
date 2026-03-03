<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_auto_pilot_logs', function (Blueprint $table) {
            // Make applicant_id nullable (was NOT NULL FK)
            $table->foreignId('hcm_applicant_id')->nullable()->change();

            // Add onboarding FK
            $table->foreignId('hcm_onboarding_id')
                ->nullable()
                ->after('hcm_applicant_id')
                ->constrained('hcm_onboardings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_auto_pilot_logs', function (Blueprint $table) {
            $table->dropForeign(['hcm_onboarding_id']);
            $table->dropColumn('hcm_onboarding_id');

            $table->foreignId('hcm_applicant_id')->nullable(false)->change();
        });
    }
};
