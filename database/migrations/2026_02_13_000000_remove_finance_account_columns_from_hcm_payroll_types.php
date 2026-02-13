<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hcm_payroll_types', function (Blueprint $t) {
            // Drop FK constraints (if they exist)
            if (Schema::hasColumn('hcm_payroll_types', 'debit_finance_account_id')) {
                try {
                    $t->dropForeign(['debit_finance_account_id']);
                } catch (\Throwable $e) {
                    // FK may not exist
                }
            }
            if (Schema::hasColumn('hcm_payroll_types', 'credit_finance_account_id')) {
                try {
                    $t->dropForeign(['credit_finance_account_id']);
                } catch (\Throwable $e) {
                    // FK may not exist
                }
            }
        });

        Schema::table('hcm_payroll_types', function (Blueprint $t) {
            if (Schema::hasColumn('hcm_payroll_types', 'debit_finance_account_id')) {
                $t->dropColumn('debit_finance_account_id');
            }
            if (Schema::hasColumn('hcm_payroll_types', 'credit_finance_account_id')) {
                $t->dropColumn('credit_finance_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_payroll_types', function (Blueprint $t) {
            $t->unsignedBigInteger('debit_finance_account_id')->nullable()->after('default_rate');
            $t->unsignedBigInteger('credit_finance_account_id')->nullable()->after('debit_finance_account_id');
        });
    }
};
