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
        Schema::create('hcm_employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hcm_employees')->cascadeOnDelete();
            $table->uuid('uuid')->unique();

            // Vertragsrahmen
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('contract_type', 100)->nullable();
            $table->string('employment_status', 100)->nullable();

            // Arbeitszeit & Urlaub
            $table->decimal('hours_per_month', 6, 2)->nullable();
            $table->unsignedSmallInteger('annual_vacation_days')->nullable();
            $table->string('working_time_model', 100)->nullable();

            // Steuer-/SV-Basis (zum Stichtag des Vertrags)
            $table->foreignId('tax_class_id')->nullable()->constrained('hcm_tax_classes')->nullOnDelete();
            $table->foreignId('tax_factor_id')->nullable()->constrained('hcm_tax_factors')->nullOnDelete();
            $table->unsignedTinyInteger('child_allowance')->nullable();
            $table->string('social_security_number', 32)->nullable();

            // Orga-Verweise (optional; IDs aus Organization-Modul)
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
            $table->index(['team_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_contracts');
    }
};
