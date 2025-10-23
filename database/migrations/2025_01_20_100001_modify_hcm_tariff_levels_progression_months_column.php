<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_tariff_levels', function (Blueprint $table) {
            $table->unsignedInteger('progression_months')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_tariff_levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('progression_months')->nullable()->change();
        });
    }
};
