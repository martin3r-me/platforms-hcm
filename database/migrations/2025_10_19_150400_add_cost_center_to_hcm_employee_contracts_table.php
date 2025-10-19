<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('hcm_employee_contracts', 'cost_center')) {
                $table->string('cost_center', 100)->nullable()->after('location_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropColumn('cost_center');
        });
    }
};
