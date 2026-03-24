<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_interview_bookings', function (Blueprint $table) {
            $table->dateTime('reminder_sent_at')->nullable()->after('booked_at');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_interview_bookings', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
