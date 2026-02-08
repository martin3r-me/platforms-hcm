<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_applicants', function (Blueprint $table) {
            $table->boolean('auto_pilot')->default(false)->after('is_active');
            $table->timestamp('auto_pilot_completed_at')->nullable()->after('auto_pilot');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_applicants', function (Blueprint $table) {
            $table->dropColumn(['auto_pilot', 'auto_pilot_completed_at']);
        });
    }
};
