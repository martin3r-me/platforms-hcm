<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_issues', function (Blueprint $table) {
            $table->string('title')->nullable()->after('issue_type_id');
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_issues', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });
    }
};
