<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_onboardings', function (Blueprint $table) {
            $table->foreignId('hcm_job_title_id')->nullable()->after('source_position_title')->constrained('hcm_job_titles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_onboardings', function (Blueprint $table) {
            $table->dropForeign(['hcm_job_title_id']);
            $table->dropColumn('hcm_job_title_id');
        });
    }
};
