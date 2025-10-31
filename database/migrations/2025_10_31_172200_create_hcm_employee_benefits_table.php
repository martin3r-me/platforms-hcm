<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hcm_employees')->onDelete('cascade');
            $table->foreignId('employee_contract_id')->nullable()->constrained('hcm_employee_contracts')->onDelete('set null');
            
            $table->string('benefit_type'); // 'bav', 'vwl', 'bkv', 'jobrad', 'other'
            $table->string('name')->nullable(); // Name des Benefits (z.B. "JobRad Model X")
            $table->text('description')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            
            // Versicherungs-/Vertragsdetails
            $table->string('insurance_company')->nullable(); // Versicherungsgesellschaft
            $table->string('contract_number')->nullable(); // Vertragsnummer
            $table->text('monthly_contribution_employee')->nullable(); // Monatlicher AN-Anteil (verschlüsselt)
            $table->text('monthly_contribution_employer')->nullable(); // Monatlicher AG-Anteil (verschlüsselt)
            $table->string('contribution_frequency')->default('monthly'); // monthly, quarterly, yearly
            
            // Spezifische Felder je Benefit-Type (in JSON)
            $table->json('benefit_specific_data')->nullable(); // z.B. für JobRad: Modell, Rahmennummer, etc.
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['employee_id', 'benefit_type', 'is_active']);
            $table->index(['employee_contract_id']);
        });
        
        // Hash-Spalten für verschlüsselte Beträge
        Schema::table('hcm_employee_benefits', function (Blueprint $table) {
            $table->string('monthly_contribution_employee_hash', 64)->nullable()->after('monthly_contribution_employee');
            $table->string('monthly_contribution_employer_hash', 64)->nullable()->after('monthly_contribution_employer');
            $table->index('monthly_contribution_employee_hash');
            $table->index('monthly_contribution_employer_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_benefits');
    }
};

