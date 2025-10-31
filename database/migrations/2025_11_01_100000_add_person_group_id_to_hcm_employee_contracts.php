<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('hcm_employee_contracts', 'person_group_id')) {
                $table->foreignId('person_group_id')->nullable()->after('employment_relationship_id')
                    ->constrained('hcm_person_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('hcm_employee_contracts', 'person_group_id')) {
                $table->dropForeign(['person_group_id']);
                $table->dropColumn('person_group_id');
            }
        });
    }
};

