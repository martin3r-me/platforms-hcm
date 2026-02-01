<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_issue_types', function (Blueprint $table) {
            $table->boolean('requires_signature')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_issue_types', function (Blueprint $table) {
            $table->dropColumn('requires_signature');
        });
    }
};
