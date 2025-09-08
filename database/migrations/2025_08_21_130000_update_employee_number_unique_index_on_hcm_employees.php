<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            // alten Unique-Index auf employee_number entfernen
            $table->dropUnique('hcm_employees_employee_number_unique');

            // neuen zusammengesetzten Unique-Index (employer_id, employee_number) hinzufügen
            $table->unique(['employer_id', 'employee_number'], 'hcm_employees_employer_employee_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            // zusammengesetzten Unique-Index entfernen
            $table->dropUnique('hcm_employees_employer_employee_number_unique');

            // ursprünglichen Unique-Index wiederherstellen
            $table->unique('employee_number');
        });
    }
};



