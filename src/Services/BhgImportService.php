<?php

namespace Platform\Hcm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmJobActivity;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmEmailAddress;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Crm\Models\CrmPostalAddress;
use Platform\Crm\Models\CrmContactLink;
use Platform\Crm\Models\CrmContactRelation;
use Platform\Crm\Models\CrmCompany;
use Carbon\Carbon;

class BhgImportService
{
    private $teamId;
    private $userId;
    private $employerId;
    private $stats = [
        'employees_created' => 0,
        'job_titles_created' => 0,
        'job_activities_created' => 0,
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
                
                // 3. Create CRM Contacts
                $this->createCrmContacts($data);
            });

            return $this->stats;
        } catch (\Exception $e) {
            Log::error('BHG Import failed: ' . $e->getMessage());
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

    private function createCrmContacts($data)
    {
        foreach ($data as $row) {
            $this->createCrmContact($row);
        }
    }

    private function createCrmContact($row)
    {
        try {
            // Create CRM Contact
            $contact = CrmContact::create([
                'team_id' => $this->teamId,
                'first_name' => $row['vorname'],
                'last_name' => $row['nachname'],
                'is_active' => true,
                'created_by_user_id' => $this->userId,
            ]);

            // Add email if available
            if (!empty($row['email'])) {
                CrmEmailAddress::create([
                    'emailable_id' => $contact->id,
                    'emailable_type' => CrmContact::class,
                    'email_address' => $row['email'],
                    'is_primary' => true,
                ]);
            }

            // Add phone numbers
            if (!empty($row['telefon'])) {
                CrmPhoneNumber::create([
                    'phoneable_id' => $contact->id,
                    'phoneable_type' => CrmContact::class,
                    'phone_number' => $row['telefon'],
                    'is_primary' => true,
                    'type' => 'work',
                ]);
            }

            if (!empty($row['mobil'])) {
                CrmPhoneNumber::create([
                    'phoneable_id' => $contact->id,
                    'phoneable_type' => CrmContact::class,
                    'phone_number' => $row['mobil'],
                    'is_primary' => false,
                    'type' => 'mobile',
                ]);
            }

            // Add address
            if (!empty($row['straße']) && !empty($row['plz']) && !empty($row['ort'])) {
                CrmPostalAddress::create([
                    'addressable_id' => $contact->id,
                    'addressable_type' => CrmContact::class,
                    'street' => $row['straße'],
                    'postal_code' => $row['plz'],
                    'city' => $row['ort'],
                    'address_line_2' => $row['adresszusatz'],
                    'is_primary' => true,
                    'type' => 'work',
                ]);
            }

            // Link to employee if exists
            $employee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->first();

            if ($employee) {
                CrmContactLink::create([
                    'contact_id' => $contact->id,
                    'linkable_id' => $employee->id,
                    'linkable_type' => HcmEmployee::class,
                ]);
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
}
