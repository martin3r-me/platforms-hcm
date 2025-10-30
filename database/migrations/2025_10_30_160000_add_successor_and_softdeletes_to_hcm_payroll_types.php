<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hcm_payroll_types', function (Blueprint $t) {
            if (!Schema::hasColumn('hcm_payroll_types', 'successor_payroll_type_id')) {
                $t->foreignId('successor_payroll_type_id')
                    ->nullable()
                    ->after('credit_finance_account_id')
                    ->constrained('hcm_payroll_types')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('hcm_payroll_types', 'deleted_at')) {
                $t->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_payroll_types', function (Blueprint $t) {
            if (Schema::hasColumn('hcm_payroll_types', 'successor_payroll_type_id')) {
                $t->dropConstrainedForeignId('successor_payroll_type_id');
            }
            if (Schema::hasColumn('hcm_payroll_types', 'deleted_at')) {
                $t->dropSoftDeletes();
            }
        });
    }
};


