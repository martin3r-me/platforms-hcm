<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->tinyInteger('schooling_level')->nullable()->after('employment_relationship_id')->comment('Tätigkeitsschlüssel Stelle 6: 1,2,3,4,9');
            $table->tinyInteger('vocational_training_level')->nullable()->after('schooling_level')->comment('Tätigkeitsschlüssel Stelle 7: 1,2,3,4,5,6,9');
            $table->boolean('is_temp_agency')->default(false)->after('vocational_training_level')->comment('Tätigkeitsschlüssel Stelle 8: Arbeitnehmerüberlassung');
            $table->char('contract_form', 1)->nullable()->after('is_temp_agency')->comment('Tätigkeitsschlüssel Stelle 9: Vertragsform (1-stellig)');

            $table->index(['schooling_level']);
            $table->index(['vocational_training_level']);
            $table->index(['is_temp_agency']);
            $table->index(['contract_form']);
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropIndex(['hcm_employee_contracts_schooling_level_index']);
            $table->dropIndex(['hcm_employee_contracts_vocational_training_level_index']);
            $table->dropIndex(['hcm_employee_contracts_is_temp_agency_index']);
            $table->dropIndex(['hcm_employee_contracts_contract_form_index']);
            $table->dropColumn(['schooling_level','vocational_training_level','is_temp_agency','contract_form']);
        });
    }
};


