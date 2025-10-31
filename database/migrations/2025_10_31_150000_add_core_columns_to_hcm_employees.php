<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('is_active');
            $table->string('gender', 16)->nullable()->after('birth_date');
            $table->string('nationality', 100)->nullable()->after('gender');
            $table->unsignedSmallInteger('children_count')->nullable()->after('nationality');
            $table->unsignedSmallInteger('disability_degree')->nullable()->after('children_count');

            // Steuer/SV (personenbezogen)
            $table->string('tax_class', 16)->nullable()->after('disability_degree');
            $table->string('church_tax', 16)->nullable()->after('tax_class');
            $table->unsignedSmallInteger('child_allowance')->nullable()->after('church_tax');
            $table->string('insurance_status', 50)->nullable()->after('child_allowance');
            $table->string('health_insurance_ik', 20)->nullable()->after('insurance_status');
            $table->string('health_insurance_name', 255)->nullable()->after('health_insurance_ik');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date',
                'gender',
                'nationality',
                'children_count',
                'disability_degree',
                'tax_class',
                'church_tax',
                'child_allowance',
                'insurance_status',
                'health_insurance_ik',
                'health_insurance_name',
            ]);
        });
    }
};


