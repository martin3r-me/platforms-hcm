<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop FK constraints using raw statements so try/catch actually works
        // (Blueprint defers execution, so try/catch inside Schema::table closures is ineffective)
        try {
            DB::statement('ALTER TABLE hcm_payroll_types DROP FOREIGN KEY hcm_payroll_types_debit_finance_account_id_foreign');
        } catch (\Throwable $e) {
            // FK may not exist
        }

        try {
            DB::statement('ALTER TABLE hcm_payroll_types DROP FOREIGN KEY hcm_payroll_types_credit_finance_account_id_foreign');
        } catch (\Throwable $e) {
            // FK may not exist
        }

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
