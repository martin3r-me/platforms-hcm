<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            // Dienstwagen & Fahrtkosten
            $table->boolean('company_car_enabled')->default(false)->after('cost_center_id');
            $table->text('travel_cost_reimbursement')->nullable()->after('company_car_enabled'); // Verschlüsselt
            
            // Befristung/Probezeit
            $table->boolean('is_fixed_term')->default(false)->after('travel_cost_reimbursement');
            $table->date('fixed_term_end_date')->nullable()->after('is_fixed_term');
            $table->date('probation_end_date')->nullable()->after('fixed_term_end_date'); // Probezeit
            $table->string('employment_relationship_type')->nullable()->after('probation_end_date'); // Beschäftigungsverhältnis
            // contract_form existiert bereits als char(1) aus früherer Migration, nicht erneut hinzufügen
            
            // Behinderung Urlaub
            $table->unsignedSmallInteger('additional_vacation_disability')->nullable()->after('probation_end_date'); // Zusatzurlaub Schwerbehinderung
            
            // Arbeitsort/Standort
            $table->string('work_location_name')->nullable()->after('additional_vacation_disability'); // Tätigkeitsstätte Name
            $table->string('work_location_address')->nullable()->after('work_location_name');
            $table->string('work_location_postal_code')->nullable()->after('work_location_address');
            $table->string('work_location_city')->nullable()->after('work_location_postal_code');
            $table->string('work_location_state')->nullable()->after('work_location_city'); // Bundesland
            $table->string('branch_name')->nullable()->after('work_location_state'); // Betriebsstätte
            
            // Rentenversicherung
            $table->string('pension_insurance_company_number')->nullable()->after('branch_name'); // BetriebsnummerRV
            $table->boolean('pension_insurance_exempt')->default(false)->after('pension_insurance_company_number'); // Rentenversicherungsfreiheit
            
            // Zusätzliche Beschäftigung (Hauptbeschäftigt, etc.)
            $table->boolean('is_primary_employment')->default(true)->after('pension_insurance_exempt'); // Hauptbeschäftigt
            $table->boolean('has_additional_employment')->default(false)->after('is_primary_employment'); // Zusätzliche Arbeitsverhältnisse
            
            // Logis (Unterkunft)
            $table->text('accommodation')->nullable()->after('has_additional_employment'); // Logis
        });
        
        // Hash-Spalte für travel_cost_reimbursement
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->string('travel_cost_reimbursement_hash', 64)->nullable()->after('travel_cost_reimbursement');
            $table->index('travel_cost_reimbursement_hash');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employee_contracts', function (Blueprint $table) {
            $table->dropIndex(['travel_cost_reimbursement_hash']);
            $table->dropColumn([
                'company_car_enabled',
                'travel_cost_reimbursement',
                'travel_cost_reimbursement_hash',
                'is_fixed_term',
                'fixed_term_end_date',
                'probation_end_date',
                'employment_relationship_type',
                // contract_form wird nicht gelöscht (existiert bereits)
                'additional_vacation_disability',
                'work_location_name',
                'work_location_address',
                'work_location_postal_code',
                'work_location_city',
                'work_location_state',
                'branch_name',
                'pension_insurance_company_number',
                'pension_insurance_exempt',
                'is_primary_employment',
                'has_additional_employment',
                'accommodation',
            ]);
        });
    }
};

