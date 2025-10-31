<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->string('payout_type')->nullable()->after('insurance_status');
            $table->string('bank_account_holder')->nullable()->after('payout_type');
            $table->string('bank_iban')->nullable()->after('bank_account_holder');
            $table->string('bank_swift')->nullable()->after('bank_iban');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropColumn(['payout_type','bank_account_holder','bank_iban','bank_swift']);
        });
    }
};


