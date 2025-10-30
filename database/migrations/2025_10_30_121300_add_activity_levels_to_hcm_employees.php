<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->tinyInteger('schooling_level')->nullable()->after('company_employee_number')->comment('Tätigkeitsschlüssel Stelle 6');
            $table->tinyInteger('vocational_training_level')->nullable()->after('schooling_level')->comment('Tätigkeitsschlüssel Stelle 7');
            $table->index(['schooling_level']);
            $table->index(['vocational_training_level']);
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropIndex(['hcm_employees_schooling_level_index']);
            $table->dropIndex(['hcm_employees_vocational_training_level_index']);
            $table->dropColumn(['schooling_level','vocational_training_level']);
        });
    }
};


