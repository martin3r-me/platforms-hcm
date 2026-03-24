<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_interviews', function (Blueprint $table) {
            $table->json('reminder_wa_template_variables')->nullable()->after('reminder_hours_before');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_interviews', function (Blueprint $table) {
            $table->dropColumn('reminder_wa_template_variables');
        });
    }
};
