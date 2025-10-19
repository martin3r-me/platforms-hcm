<?php

namespace Platform\Hcm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmJobActivity;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmEmailAddress;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Crm\Models\CrmPostalAddress;
use Platform\Crm\Models\CrmContactLink;
use Platform\Crm\Models\CrmContactRelation;
use Platform\Crm\Models\CrmCompany;
use Carbon\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

class BhgImportService
{
    private $teamId;
    private $userId;
    private $employerId;
    private $stats = [
        'employees_created' => 0,
        'job_titles_created' => 0,
        'job_activities_created' => 0,
        'contracts_created' => 0,
        'contract_activity_links_created' => 0,
        'crm_contacts_created' => 0,
        'crm_company_relations_created' => 0,
        'errors' => []
    ];

    public function __construct($teamId, $userId, $employerId = null)
    {
        $this->teamId = $teamId;
        $this->userId = $userId;
        $this->employerId = $employerId;
        
        // Wenn employerId gesetzt ist, team_id vom Arbeitgeber übernehmen
        if ($employerId) {
            $employer = HcmEmployer::find($employerId);
            if ($employer) {
                $this->teamId = $employer->team_id;
            }
        }
    }

    public function importFromCsv($csvPath)
    {
        try {
            $data = $this->parseCsv($csvPath);
            
            DB::transaction(function () use ($data) {
                // 1. Import Job Titles and Activities first
                $this->importJobTitlesAndActivities($data);
                
                // 2. Import Employees
                $this->importEmployees($data);
                
                // 3. Create Contracts
                $this->createContracts($data);
                
                // 4. Create CRM Contacts
                $this->createCrmContacts($data);
            });

            return $this->stats;
        } catch (\Exception $e) {
            Log::error('BHG Import failed: ' . $e->getMessage());
            $this->stats['errors'][] = $e->getMessage();
            return $this->stats;
        }
    }

    public function dryRunFromCsv($csvPath)
    {
        try {
            $data = $this->parseCsv($csvPath);
            
            // 1. Analyze Job Titles and Activities
            $this->analyzeJobTitlesAndActivities($data);
            
            // 2. Analyze Employees
            $this->analyzeEmployees($data);
            
            // 3. Analyze Contracts
            $this->analyzeContracts($data);
            
            // 4. Analyze CRM Contacts
            $this->analyzeCrmContacts($data);

            return $this->stats;
        } catch (\Exception $e) {
            Log::error('BHG Dry Run failed: ' . $e->getMessage());
            $this->stats['errors'][] = $e->getMessage();
            return $this->stats;
        }
    }

    private function parseCsv($csvPath)
    {
        $data = [];
        $handle = fopen($csvPath, 'r');
        
        if (!$handle) {
            throw new \Exception('Could not open CSV file');
        }

        // Skip header
        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 17) {
                $data[] = [
                    'personalnummer' => $row[0],
                    'nachname' => $row[1],
                    'vorname' => $row[2],
                    'geschlecht' => $row[3],
                    'straße' => $row[4],
                    'plz' => $row[5],
                    'ort' => $row[6],
                    'adresszusatz' => $row[7],
                    'tätigkeit' => $row[8],
                    'stellenbezeichnung' => $row[9],
                    'ks_schlüssel' => $row[10],
                    'eintrittsdatum' => $row[11],
                    'austrittsdatum' => $row[12],
                    'konzerneintritt' => $row[13],
                    'telefon' => $row[14],
                    'mobil' => $row[15],
                    'email' => $row[16],
                ];
            }
        }

        fclose($handle);
        return $data;
    }

    private function importJobTitlesAndActivities($data)
    {
        $jobTitles = [];
        $jobActivities = [];

        foreach ($data as $row) {
            // Collect unique job titles
            if (!empty($row['stellenbezeichnung']) && !in_array($row['stellenbezeichnung'], $jobTitles)) {
                $jobTitles[] = $row['stellenbezeichnung'];
            }

            // Collect unique job activities
            if (!empty($row['tätigkeit']) && !in_array($row['tätigkeit'], $jobActivities)) {
                $jobActivities[] = $row['tätigkeit'];
            }
        }

        // Create Job Titles
        foreach ($jobTitles as $title) {
            $this->createJobTitle($title);
        }

        // Create Job Activities
        foreach ($jobActivities as $activity) {
            $this->createJobActivity($activity);
        }
    }

    private function createJobTitle($name)
    {
        $existing = HcmJobTitle::where('team_id', $this->teamId)
            ->where('name', $name)
            ->first();

        if (!$existing) {
            HcmJobTitle::create([
                'team_id' => $this->teamId,
                'code' => 'JT_' . substr(md5($name), 0, 8),
                'name' => $name,
                'short_name' => substr($name, 0, 50),
                'is_active' => true,
                'created_by_user_id' => $this->userId,
            ]);
            $this->stats['job_titles_created']++;
        }
    }

    private function createJobActivity($name)
    {
        $existing = HcmJobActivity::where('team_id', $this->teamId)
            ->where('name', $name)
            ->first();

        if (!$existing) {
            HcmJobActivity::create([
                'team_id' => $this->teamId,
                'code' => 'JA_' . substr(md5($name), 0, 8),
                'name' => $name,
                'short_name' => substr($name, 0, 50),
                'is_active' => true,
                'created_by_user_id' => $this->userId,
            ]);
            $this->stats['job_activities_created']++;
        }
    }

    private function importEmployees($data)
    {
        foreach ($data as $row) {
            $this->createEmployee($row);
        }
    }

    private function createEmployee($row)
    {
        try {
            // Prüfen ob Mitarbeiter bereits existiert
            $existingEmployee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->where('employer_id', $this->employerId)
                ->first();
            
            if ($existingEmployee) {
                // Mitarbeiter existiert bereits - überspringen
                return $existingEmployee;
            }
            
            $employee = HcmEmployee::create([
                'team_id' => $this->teamId,
                'employee_number' => $row['personalnummer'],
                'employer_id' => $this->employerId,
                'is_active' => empty($row['austrittsdatum']),
                'created_by_user_id' => $this->userId,
            ]);

            $this->stats['employees_created']++;
            return $employee;
        } catch (\Exception $e) {
            $this->stats['errors'][] = "Employee {$row['personalnummer']}: " . $e->getMessage();
        }
    }

    private function createContracts($data)
    {
        foreach ($data as $row) {
            $this->createContract($row);
        }
    }

    private function createContract($row)
    {
        try {
            $employee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->first();

            if (!$employee) {
                return;
            }

            // Prüfen ob Vertrag bereits existiert (basierend auf Eintrittsdatum)
            $startDate = $row['eintrittsdatum'] ? Carbon::createFromFormat('d.m.Y', $row['eintrittsdatum']) : null;
            $endDate = $row['austrittsdatum'] ? Carbon::createFromFormat('d.m.Y', $row['austrittsdatum']) : null;
            
            $existingContract = HcmEmployeeContract::where('employee_id', $employee->id)
                ->where('start_date', $startDate)
                ->first();
            
            if ($existingContract) {
                // Vertrag existiert bereits - überspringen
                return;
            }
            
            // Neuen Vertrag erstellen
            $contract = HcmEmployeeContract::create([
                'employee_id' => $employee->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'contract_type' => 'unbefristet', // Standard
                'employment_status' => 'aktiv',
                'cost_center' => $row['ks_schlüssel'], // Kostenstelle aus CSV
                'is_active' => empty($row['austrittsdatum']),
                'created_by_user_id' => $this->userId,
                'team_id' => $this->teamId,
            ]);

            // Job Title verknüpfen
            if (!empty($row['stellenbezeichnung'])) {
                $jobTitle = HcmJobTitle::where('team_id', $this->teamId)
                    ->where('name', $row['stellenbezeichnung'])
                    ->first();
                
                if ($jobTitle) {
                    $contract->update(['job_title_id' => $jobTitle->id]);
                }
            }

            // Job Activities verknüpfen
            if (!empty($row['tätigkeit'])) {
                $jobActivity = HcmJobActivity::where('team_id', $this->teamId)
                    ->where('name', $row['tätigkeit'])
                    ->first();
                
                if ($jobActivity) {
                    DB::table('hcm_employee_contract_activity_links')->insert([
                        'contract_id' => $contract->id,
                        'job_activity_id' => $jobActivity->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->stats['contract_activity_links_created']++;
                }
            }

            $this->stats['contracts_created']++;
        } catch (\Exception $e) {
            $this->stats['errors'][] = "Contract {$row['personalnummer']}: " . $e->getMessage();
        }
    }

    private function createCrmContacts($data)
    {
        foreach ($data as $row) {
            $this->createCrmContact($row);
        }
    }

    private function createCrmContact($row)
    {
        try {
            // Prüfen ob Kontakt bereits existiert (basierend auf Name und E-Mail)
            $contact = CrmContact::where('team_id', $this->teamId)
                ->where('first_name', $row['vorname'])
                ->where('last_name', $row['nachname'])
                ->when(!empty($row['email']), function($query) use ($row) {
                    return $query->whereHas('emailAddresses', function($q) use ($row) {
                        $q->where('email_address', $row['email']);
                    });
                })
                ->first();

            if (!$contact) {
                // Create CRM Contact
                $contact = CrmContact::create([
                    'team_id' => $this->teamId,
                    'first_name' => $row['vorname'],
                    'last_name' => $row['nachname'],
                    'is_active' => true,
                    'created_by_user_id' => $this->userId,
                ]);
            }

            // Add email if available and not already exists
            if (!empty($row['email'])) {
                $existingEmail = CrmEmailAddress::where('emailable_id', $contact->id)
                    ->where('emailable_type', CrmContact::class)
                    ->where('email_address', $row['email'])
                    ->first();
                
                if (!$existingEmail) {
                    CrmEmailAddress::create([
                        'emailable_id' => $contact->id,
                        'emailable_type' => CrmContact::class,
                        'email_address' => $row['email'],
                        'email_type_id' => 1, // Standard E-Mail-Typ
                        'is_primary' => true,
                    ]);
                }
            }

            // Add phone numbers
            if (!empty($row['telefon'])) {
                $this->createPhoneNumber($contact, $row['telefon'], 1, true);
            }

            if (!empty($row['mobil'])) {
                $this->createPhoneNumber($contact, $row['mobil'], 2, false);
            }

            // Add address
            if (!empty($row['straße']) && !empty($row['plz']) && !empty($row['ort'])) {
                CrmPostalAddress::create([
                    'addressable_id' => $contact->id,
                    'addressable_type' => CrmContact::class,
                    'street' => $row['straße'],
                    'postal_code' => $row['plz'],
                    'city' => $row['ort'],
                    'additional_info' => $row['adresszusatz'],
                    'address_type_id' => 1, // Standard Adress-Typ
                    'is_primary' => true,
                ]);
            }

            // Link to employee if exists
            $employee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->first();

            if ($employee) {
                // Prüfen ob Link bereits existiert
                $existingLink = CrmContactLink::where('contact_id', $contact->id)
                    ->where('linkable_id', $employee->id)
                    ->where('linkable_type', HcmEmployee::class)
                    ->first();
                
                if (!$existingLink) {
                    CrmContactLink::create([
                        'contact_id' => $contact->id,
                        'linkable_id' => $employee->id,
                        'linkable_type' => HcmEmployee::class,
                        'team_id' => $this->teamId,
                        'created_by_user_id' => $this->userId,
                    ]);
                }
            }

            // Create company relation if employer exists
            if ($this->employerId) {
                $this->createCompanyRelation($contact, $row);
            }

            $this->stats['crm_contacts_created']++;
        } catch (\Exception $e) {
            $this->stats['errors'][] = "CRM Contact {$row['personalnummer']}: " . $e->getMessage();
        }
    }

    private function createCompanyRelation($contact, $row)
    {
        try {
            // Find or create CRM company for the employer
            $employer = HcmEmployer::find($this->employerId);
            if (!$employer) {
                return;
            }

            $company = CrmCompany::where('team_id', $this->teamId)
                ->where('name', $employer->display_name)
                ->first();

            if (!$company) {
                $company = CrmCompany::create([
                    'team_id' => $this->teamId,
                    'name' => $employer->display_name,
                    'is_active' => true,
                    'created_by_user_id' => $this->userId,
                ]);
            }

            // Create contact relation
            CrmContactRelation::create([
                'contact_id' => $contact->id,
                'company_id' => $company->id,
                'relation_type_id' => 1, // Employee relation type
                'position' => $row['stellenbezeichnung'] ?: $row['tätigkeit'],
                'start_date' => $row['eintrittsdatum'] ? Carbon::createFromFormat('d.m.Y', $row['eintrittsdatum']) : null,
                'end_date' => $row['austrittsdatum'] ? Carbon::createFromFormat('d.m.Y', $row['austrittsdatum']) : null,
                'is_primary' => true,
                'is_active' => empty($row['austrittsdatum']),
            ]);

            $this->stats['crm_company_relations_created']++;
        } catch (\Exception $e) {
            $this->stats['errors'][] = "Company relation {$row['personalnummer']}: " . $e->getMessage();
        }
    }

    private function createPhoneNumber($contact, $phoneInput, $phoneTypeId, $isPrimary)
    {
        try {
            // Prüfen ob Telefonnummer bereits existiert
            $existingPhone = CrmPhoneNumber::where('phoneable_id', $contact->id)
                ->where('phoneable_type', CrmContact::class)
                ->where('raw_input', $phoneInput)
                ->where('phone_type_id', $phoneTypeId)
                ->first();
            
            if ($existingPhone) {
                return; // Telefonnummer bereits vorhanden
            }
            
            $phoneUtil = PhoneNumberUtil::getInstance();
            
            // Versuche deutsche Nummer zu parsen (DE als Standard)
            $phoneNumber = $phoneUtil->parse($phoneInput, 'DE');
            
            if (!$phoneUtil->isValidNumber($phoneNumber)) {
                // Fallback: Nur raw_input speichern
                CrmPhoneNumber::create([
                    'phoneable_id' => $contact->id,
                    'phoneable_type' => CrmContact::class,
                    'raw_input' => $phoneInput,
                    'phone_type_id' => $phoneTypeId,
                    'is_primary' => $isPrimary,
                ]);
                return;
            }
            
            // Telefonnummer korrekt formatieren
            $phoneData = [
                'phoneable_id' => $contact->id,
                'phoneable_type' => CrmContact::class,
                'raw_input' => $phoneInput,
                'international' => $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164),
                'national' => $phoneUtil->format($phoneNumber, PhoneNumberFormat::NATIONAL),
                'country_code' => $phoneUtil->getRegionCodeForNumber($phoneNumber),
                'phone_type_id' => $phoneTypeId,
                'is_primary' => $isPrimary,
            ];
            
            CrmPhoneNumber::create($phoneData);
            
        } catch (NumberParseException $e) {
            // Fallback: Nur raw_input speichern bei Parsing-Fehlern
            CrmPhoneNumber::create([
                'phoneable_id' => $contact->id,
                'phoneable_type' => CrmContact::class,
                'raw_input' => $phoneInput,
                'phone_type_id' => $phoneTypeId,
                'is_primary' => $isPrimary,
            ]);
        } catch (\Exception $e) {
            // Fallback: Nur raw_input speichern bei anderen Fehlern
            CrmPhoneNumber::create([
                'phoneable_id' => $contact->id,
                'phoneable_type' => CrmContact::class,
                'raw_input' => $phoneInput,
                'phone_type_id' => $phoneTypeId,
                'is_primary' => $isPrimary,
            ]);
        }
    }

    private function analyzeJobTitlesAndActivities($data)
    {
        $jobTitles = [];
        $jobActivities = [];

        foreach ($data as $row) {
            // Collect unique job titles
            if (!empty($row['stellenbezeichnung']) && !in_array($row['stellenbezeichnung'], $jobTitles)) {
                $jobTitles[] = $row['stellenbezeichnung'];
            }

            // Collect unique job activities
            if (!empty($row['tätigkeit']) && !in_array($row['tätigkeit'], $jobActivities)) {
                $jobActivities[] = $row['tätigkeit'];
            }
        }

        // Count Job Titles that would be created
        foreach ($jobTitles as $title) {
            $existing = HcmJobTitle::where('team_id', $this->teamId)
                ->where('name', $title)
                ->first();

            if (!$existing) {
                $this->stats['job_titles_created']++;
            }
        }

        // Count Job Activities that would be created
        foreach ($jobActivities as $activity) {
            $existing = HcmJobActivity::where('team_id', $this->teamId)
                ->where('name', $activity)
                ->first();

            if (!$existing) {
                $this->stats['job_activities_created']++;
            }
        }
    }

    private function analyzeEmployees($data)
    {
        foreach ($data as $row) {
            $existingEmployee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->where('employer_id', $this->employerId)
                ->first();
            
            if (!$existingEmployee) {
                $this->stats['employees_created']++;
            }
        }
    }

    private function analyzeContracts($data)
    {
        foreach ($data as $row) {
            $employee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->first();

            if ($employee) {
                $startDate = $row['eintrittsdatum'] ? Carbon::createFromFormat('d.m.Y', $row['eintrittsdatum']) : null;
                
                $existingContract = HcmEmployeeContract::where('employee_id', $employee->id)
                    ->where('start_date', $startDate)
                    ->first();
                
                if (!$existingContract) {
                    $this->stats['contracts_created']++;
                    
                    // Count activity links
                    if (!empty($row['tätigkeit'])) {
                        $this->stats['contract_activity_links_created']++;
                    }
                }
            }
        }
    }

    private function analyzeCrmContacts($data)
    {
        foreach ($data as $row) {
            // Check if contact would be created
            $contact = CrmContact::where('team_id', $this->teamId)
                ->where('first_name', $row['vorname'])
                ->where('last_name', $row['nachname'])
                ->when(!empty($row['email']), function($query) use ($row) {
                    return $query->whereHas('emailAddresses', function($q) use ($row) {
                        $q->where('email_address', $row['email']);
                    });
                })
                ->first();

            if (!$contact) {
                $this->stats['crm_contacts_created']++;
            }

            // Count company relations
            if ($this->employerId) {
                $this->stats['crm_company_relations_created']++;
            }
        }
    }
}
