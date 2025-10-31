<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->string('social_security_number_hash', 64)->nullable()->after('social_security_number');
            $table->index('social_security_number_hash');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropIndex(['social_security_number_hash']);
            $table->dropColumn(['social_security_number_hash']);
        });
    }
};


