<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            // Notfallkontakt
            $table->string('emergency_contact_name')->nullable()->after('health_insurance_name');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            
            // Zusätzliche Personaldaten
            $table->string('birth_surname')->nullable()->after('birth_date'); // Geburtsname
            $table->string('birth_place')->nullable()->after('birth_surname'); // Geburtsort
            $table->string('birth_country')->nullable()->after('birth_place'); // Geburtsland
            $table->string('title')->nullable()->after('birth_country'); // Dr., Prof., etc.
            $table->string('name_prefix')->nullable()->after('title'); // Von, Zu, etc.
            $table->string('name_suffix')->nullable()->after('name_prefix'); // Zusatzwort
            
            // Arbeitserlaubnis/Aufenthalt
            $table->boolean('permanent_residence_permit')->nullable()->after('name_suffix'); // Unbefristete Aufenthaltserlaubnis
            $table->date('work_permit_until')->nullable()->after('permanent_residence_permit'); // Arbeitserlaubnis bis
            $table->string('border_worker_country')->nullable()->after('work_permit_until'); // Grenzgänger Land
            
            // Behinderung Details
            $table->boolean('has_disability_id')->nullable()->after('disability_degree'); // Behindertenausweis
            $table->string('disability_id_number')->nullable()->after('has_disability_id'); // Behindertenausweisnummer
            $table->date('disability_id_valid_from')->nullable()->after('disability_id_number');
            $table->date('disability_id_valid_until')->nullable()->after('disability_id_valid_from');
            $table->string('disability_office')->nullable()->after('disability_id_valid_until'); // Dienststelle
            $table->string('disability_office_location')->nullable()->after('disability_office'); // Ort der Dienststelle
            
            // Vorgesetzter/Organisation
            $table->foreignId('supervisor_id')->nullable()->after('employer_id')->constrained('hcm_employees')->nullOnDelete();
            $table->foreignId('deputy_id')->nullable()->after('supervisor_id')->constrained('hcm_employees')->nullOnDelete();
            $table->string('alias')->nullable()->after('deputy_id');
            
            // Schulungen/Nachweise (hygiene_training_date wird über hcm_employee_trainings erfasst)
            $table->date('parent_eligibility_proof_date')->nullable()->after('alias');
            
            // Sonstiges
            $table->string('business_email')->nullable()->after('parent_eligibility_proof_date'); // EMailGeschaeftlich
            $table->string('alternative_employee_number')->nullable()->after('business_email'); // Abweichende PersonalNr
            
            // Saisonarbeitnehmer, Erwerbsminderungsrentner
            $table->boolean('is_seasonal_worker')->default(false)->after('alternative_employee_number');
            $table->boolean('is_disability_pensioner')->default(false)->after('is_seasonal_worker');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropConstrainedForeignId('deputy_id');
            $table->dropColumn([
                'emergency_contact_name',
                'emergency_contact_phone',
                'birth_surname',
                'birth_place',
                'birth_country',
                'title',
                'name_prefix',
                'name_suffix',
                'permanent_residence_permit',
                'work_permit_until',
                'border_worker_country',
                'has_disability_id',
                'disability_id_number',
                'disability_id_valid_from',
                'disability_id_valid_until',
                'disability_office',
                'disability_office_location',
                'alias',
                'parent_eligibility_proof_date',
                'business_email',
                'alternative_employee_number',
                'is_seasonal_worker',
                'is_disability_pensioner',
            ]);
        });
    }
};

