<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_interviews', function (Blueprint $table) {
            $table->unsignedBigInteger('reminder_wa_template_id')->nullable()->after('status');
            $table->unsignedInteger('reminder_hours_before')->nullable()->after('reminder_wa_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_interviews', function (Blueprint $table) {
            $table->dropColumn(['reminder_wa_template_id', 'reminder_hours_before']);
        });
    }
};
