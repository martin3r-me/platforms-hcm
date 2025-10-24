<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hcm_health_insurance_companies', function (Blueprint $table) {
            $table->string('ik_number', 20)->nullable()->after('code')->comment('Institutionskennzeichen (IK-Nummer)');
            $table->index('ik_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hcm_health_insurance_companies', function (Blueprint $table) {
            $table->dropIndex(['ik_number']);
            $table->dropColumn('ik_number');
        });
    }
};
