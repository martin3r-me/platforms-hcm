<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_onboardings', function (Blueprint $table) {
            $table->string('enrichment_status')->nullable()->after('progress');
            $table->boolean('auto_pilot')->default(false)->after('enrichment_status');
            $table->timestamp('auto_pilot_completed_at')->nullable()->after('auto_pilot');
            $table->foreignId('preferred_comms_channel_id')->nullable()->after('auto_pilot_completed_at')->constrained('comms_channels')->nullOnDelete();
            $table->string('source_position_title')->nullable()->after('preferred_comms_channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_onboardings', function (Blueprint $table) {
            $table->dropForeign(['preferred_comms_channel_id']);
            $table->dropColumn([
                'enrichment_status',
                'auto_pilot',
                'auto_pilot_completed_at',
                'preferred_comms_channel_id',
                'source_position_title',
            ]);
        });
    }
};
