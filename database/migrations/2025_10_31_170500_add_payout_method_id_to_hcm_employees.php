<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->foreignId('payout_method_id')->nullable()->after('payout_type')->constrained('hcm_payout_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_method_id');
        });
    }
};


