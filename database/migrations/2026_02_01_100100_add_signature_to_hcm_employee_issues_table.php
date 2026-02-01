<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_issues', function (Blueprint $table) {
            $table->text('signature_data')->nullable()->after('notes');
            $table->timestamp('signed_at')->nullable()->after('signature_data');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_issues', function (Blueprint $table) {
            $table->dropColumn(['signature_data', 'signed_at']);
        });
    }
};
