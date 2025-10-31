<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->string('tax_id_number_hash', 64)->nullable()->after('tax_id_number');
            $table->string('bank_iban_hash', 64)->nullable()->after('bank_iban');
            $table->string('bank_swift_hash', 64)->nullable()->after('bank_swift');
            $table->string('bank_account_holder_hash', 64)->nullable()->after('bank_account_holder');

            $table->index('tax_id_number_hash');
            $table->index('bank_iban_hash');
            $table->index('bank_swift_hash');
            $table->index('bank_account_holder_hash');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropIndex(['tax_id_number_hash']);
            $table->dropIndex(['bank_iban_hash']);
            $table->dropIndex(['bank_swift_hash']);
            $table->dropIndex(['bank_account_holder_hash']);
            $table->dropColumn(['tax_id_number_hash','bank_iban_hash','bank_swift_hash','bank_account_holder_hash']);
        });
    }
};


