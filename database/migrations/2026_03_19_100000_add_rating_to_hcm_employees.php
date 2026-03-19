<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
};
