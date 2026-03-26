<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_onboarding_contracts', function (Blueprint $table) {
            $table->json('pre_signing_data')->nullable()->after('signature_data');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_onboarding_contracts', function (Blueprint $table) {
            $table->dropColumn('pre_signing_data');
        });
    }
};
