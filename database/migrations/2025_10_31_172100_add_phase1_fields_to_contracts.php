<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dienstwagen & Fahrtkosten
        if (!Schema::hasColumn('hcm_employee_contracts', 'company_car_enabled')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->boolean('company_car_enabled')->default(false)->after('cost_center_id');
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'travel_cost_reimbursement')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->text('travel_cost_reimbursement')->nullable()->after('company_car_enabled'); // Verschlüsselt
            });
        }
        
        // Befristung/Probezeit
        if (!Schema::hasColumn('hcm_employee_contracts', 'is_fixed_term')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->boolean('is_fixed_term')->default(false)->after('travel_cost_reimbursement');
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'fixed_term_end_date')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->date('fixed_term_end_date')->nullable()->after('is_fixed_term');
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'probation_end_date')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->date('probation_end_date')->nullable()->after('fixed_term_end_date'); // Probezeit
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'employment_relationship_type')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('employment_relationship_type')->nullable()->after('probation_end_date'); // Beschäftigungsverhältnis
            });
        }
        // contract_form existiert bereits als char(1) aus früherer Migration, nicht erneut hinzufügen
        
        // Behinderung Urlaub
        if (!Schema::hasColumn('hcm_employee_contracts', 'additional_vacation_disability')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->unsignedSmallInteger('additional_vacation_disability')->nullable()->after('probation_end_date'); // Zusatzurlaub Schwerbehinderung
            });
        }
        
        // Arbeitsort/Standort
        if (!Schema::hasColumn('hcm_employee_contracts', 'work_location_name')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('work_location_name')->nullable()->after('additional_vacation_disability'); // Tätigkeitsstätte Name
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'work_location_address')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('work_location_address')->nullable()->after('work_location_name');
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'work_location_postal_code')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('work_location_postal_code')->nullable()->after('work_location_address');
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'work_location_city')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('work_location_city')->nullable()->after('work_location_postal_code');
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'work_location_state')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('work_location_state')->nullable()->after('work_location_city'); // Bundesland
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'branch_name')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('branch_name')->nullable()->after('work_location_state'); // Betriebsstätte
            });
        }
        
        // Rentenversicherung
        if (!Schema::hasColumn('hcm_employee_contracts', 'pension_insurance_company_number')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('pension_insurance_company_number')->nullable()->after('branch_name'); // BetriebsnummerRV
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'pension_insurance_exempt')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->boolean('pension_insurance_exempt')->default(false)->after('pension_insurance_company_number'); // Rentenversicherungsfreiheit
            });
        }
        
        // Zusätzliche Beschäftigung (Hauptbeschäftigt, etc.)
        if (!Schema::hasColumn('hcm_employee_contracts', 'is_primary_employment')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->boolean('is_primary_employment')->default(true)->after('pension_insurance_exempt'); // Hauptbeschäftigt
            });
        }
        if (!Schema::hasColumn('hcm_employee_contracts', 'has_additional_employment')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->boolean('has_additional_employment')->default(false)->after('is_primary_employment'); // Zusätzliche Arbeitsverhältnisse
            });
        }
        
        // Logis (Unterkunft)
        if (!Schema::hasColumn('hcm_employee_contracts', 'accommodation')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->text('accommodation')->nullable()->after('has_additional_employment'); // Logis
            });
        }
        
        // Hash-Spalte für travel_cost_reimbursement
        if (Schema::hasColumn('hcm_employee_contracts', 'travel_cost_reimbursement') && !Schema::hasColumn('hcm_employee_contracts', 'travel_cost_reimbursement_hash')) {
            Schema::table('hcm_employee_contracts', function (Blueprint $table) {
                $table->string('travel_cost_reimbursement_hash', 64)->nullable()->after('travel_cost_reimbursement');
                $table->index('travel_cost_reimbursement_hash');
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'company_car_enabled', 'travel_cost_reimbursement', 'travel_cost_reimbursement_hash',
            'is_fixed_term', 'fixed_term_end_date', 'probation_end_date',
            'employment_relationship_type', 'additional_vacation_disability',
            'work_location_name', 'work_location_address', 'work_location_postal_code',
            'work_location_city', 'work_location_state', 'branch_name',
            'pension_insurance_company_number', 'pension_insurance_exempt',
            'is_primary_employment', 'has_additional_employment', 'accommodation',
        ];

        Schema::table('hcm_employee_contracts', function (Blueprint $table) use ($columns) {
            if (Schema::hasColumn('hcm_employee_contracts', 'travel_cost_reimbursement_hash')) {
                $table->dropIndex(['travel_cost_reimbursement_hash']);
            }

            $existing = array_filter($columns, fn ($col) => Schema::hasColumn('hcm_employee_contracts', $col));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};

