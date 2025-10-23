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
use Platform\Organization\Models\OrganizationCostCenter;
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
        'employees_updated' => 0,
        'job_titles_created' => 0,
        'job_activities_created' => 0,
        'cost_centers_created' => 0,
        'contracts_created' => 0,
        'contracts_updated' => 0,
        'contract_title_links_created' => 0,
        'contract_activity_links_created' => 0,
        'crm_contacts_created' => 0,
        'crm_contacts_updated' => 0,
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
            
            // Parse additional data from Ergänzungsdatei
            $additionalData = $this->parseAdditionalData($csvPath);
            
            DB::transaction(function () use ($data, $additionalData) {
                // 1. Import Cost Centers first
                $this->importCostCenters($data);
                
                // 2. Import Job Titles and Activities
                $this->importJobTitlesAndActivities($data);
                
                // 3. Import Employees
                $this->importEmployees($data);
                
                // 4. Create Contracts
                $this->createContracts($data);
                
                // 5. Create CRM Contacts with additional data
                $this->createCrmContacts($data, $additionalData);
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
            
            // 1. Analyze Cost Centers
            $this->analyzeCostCenters($data);
            
            // 2. Analyze Job Titles and Activities
            $this->analyzeJobTitlesAndActivities($data);
            
            // 3. Analyze Employees
            $this->analyzeEmployees($data);
            
            // 4. Analyze Contracts
            $this->analyzeContracts($data);
            
            // 5. Analyze CRM Contacts
            $this->analyzeCrmContacts($data);
            
            // 5. Test actual database operations (but rollback)
            $this->testDatabaseOperations($data);

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

        // Skip header line
        fgetcsv($handle, 0, ';'); // "Personalnummer;Nachname;..."

        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowCount++;
            echo "DEBUG: Processing row {$rowCount}: " . implode(' | ', array_slice($row, 0, 5)) . "\n";
            
            if (count($row) >= 18) {
                // Nur echte Datensätze verarbeiten (Personalnummer muss numerisch sein)
                $personalnummer = trim($row[0]);
                
                // Einfache Filterung: Nur numerische Personalnummern
                if (empty($personalnummer) || !is_numeric($personalnummer)) {
                    echo "DEBUG: Skipping row {$rowCount} - not numeric personalnummer: '{$personalnummer}'\n";
                    continue;
                }
                
                echo "DEBUG: Valid row {$rowCount} - Personalnummer: '{$personalnummer}', Email: '{$row[17]}', Telefon: '{$row[15]}', Mobil: '{$row[16]}'\n";
                
                $data[] = [
                    'personalnummer' => $personalnummer,
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
                    'soll_stunden' => $row[11],
                    'eintrittsdatum' => $row[12],
                    'austrittsdatum' => $row[13],
                    'konzerneintritt' => $row[14],
                    'telefon' => $row[15],
                    'mobil' => $row[16],
                    'email' => $row[17],
                ];
            } else {
                echo "DEBUG: Skipping row {$rowCount} - insufficient columns: " . count($row) . "\n";
            }
        }

        fclose($handle);
        echo "DEBUG: Total valid rows parsed: " . count($data) . "\n";
        return $data;
    }

    private function parseAdditionalData($csvPath)
    {
        $additionalData = [];
        $additionalPath = str_replace('20102025_Mitarbeiterliste.csv', '20102025_Mitarbeiterliste_Ergaezung.csv', $csvPath);
        
        if (!file_exists($additionalPath)) {
            echo "DEBUG: Additional data file not found: {$additionalPath}\n";
            return $additionalData;
        }
        
        $handle = fopen($additionalPath, 'r');
        if (!$handle) {
            echo "DEBUG: Could not open additional data file: {$additionalPath}\n";
            return $additionalData;
        }

        // Skip header
        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 7) {
                $personalnummer = trim($row[0]);
                if (!empty($personalnummer) && is_numeric($personalnummer)) {
                    $additionalData[$personalnummer] = [
                        'ks_schlüssel' => trim($row[1]),
                        'title' => trim($row[2]),
                        'birthdate' => trim($row[3]),
                        'salutation' => trim($row[4]),
                        'countrycode' => trim($row[5]),
                        'gender' => trim($row[6]),
                    ];
                }
            }
        }

        fclose($handle);
        echo "DEBUG: Additional data parsed for " . count($additionalData) . " employees\n";
        echo "DEBUG: ===========================================\n";
        echo "DEBUG: ERGÄNZUNGSDATEI SUCCESSFULLY PARSED!\n";
        echo "DEBUG: ===========================================\n";
        return $additionalData;
    }

    private function importCostCenters($data)
    {
        $costCenters = [];
        
        foreach ($data as $row) {
            $ksSchlüssel = trim($row['ks_schlüssel']);
            if (!empty($ksSchlüssel) && !in_array($ksSchlüssel, $costCenters)) {
                $costCenters[] = $ksSchlüssel;
                
                // Prüfe ob Kostenstelle bereits existiert
                $existingCostCenter = OrganizationCostCenter::where('team_id', $this->teamId)
                    ->where('code', $ksSchlüssel)
                    ->first();
                
                if (!$existingCostCenter) {
                    OrganizationCostCenter::create([
                        'code' => $ksSchlüssel,
                        'name' => $ksSchlüssel, // Code und Name gleich halten
                        'team_id' => $this->teamId,
                        'user_id' => $this->userId,
                        'description' => 'Importiert aus Mitarbeiterliste',
                        'is_active' => true,
                    ]);
                    
                    $this->stats['cost_centers_created']++;
                }
            }
        }
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
            // Prüfe ob Code bereits existiert
            $code = 'JA_' . substr(md5($name), 0, 8);
            $existingCode = HcmJobActivity::where('team_id', $this->teamId)
                ->where('code', $code)
                ->first();
            
            if ($existingCode) {
                // Füge Zeitstempel hinzu um Eindeutigkeit zu gewährleisten
                $code = 'JA_' . substr(md5($name . time()), 0, 8);
            }
            
            HcmJobActivity::create([
                'team_id' => $this->teamId,
                'code' => $code,
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
                // Mitarbeiter existiert bereits - prüfen ob Status aktualisiert werden muss
                $isActive = empty($row['austrittsdatum']);
                if ($existingEmployee->is_active !== $isActive) {
                    $existingEmployee->update(['is_active' => $isActive]);
                    $this->stats['employees_updated']++;
                }
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

    private function parseDateValue($dateString)
    {
        if (empty($dateString) || trim($dateString) === '') {
            echo "DEBUG: Empty date string\n";
            return null;
        }
        
        $dateString = trim($dateString);
        echo "DEBUG: Parsing date: '{$dateString}'\n";
        
        try {
            // Versuche verschiedene Formate
            $formats = ['d.m.Y', 'd.m.y', 'Y-m-d', 'd-m-Y'];
            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $dateString);
                    if ($date && $date->format($format) === $dateString) {
                        echo "DEBUG: Successfully parsed date '{$dateString}' with format '{$format}'\n";
                        return $date;
                    }
                } catch (\Exception $e) {
                    echo "DEBUG: Failed to parse '{$dateString}' with format '{$format}': " . $e->getMessage() . "\n";
                }
            }
        } catch (\Exception $e) {
            echo "DEBUG: General parsing error for '{$dateString}': " . $e->getMessage() . "\n";
        }
        
        echo "DEBUG: Could not parse date '{$dateString}' - returning null\n";
        return null;
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

            // Robuste Datumsparsing
            $startDate = $this->parseDateValue($row['eintrittsdatum']);
            $endDate = $this->parseDateValue($row['austrittsdatum']);
            
            // Einfachste Prüfung: Nur Personalnummer (da jeder Mitarbeiter nur einen Vertrag haben sollte)
            $existingContract = HcmEmployeeContract::where('employee_id', $employee->id)->first();
            
            if ($existingContract) {
                // Bestehender Vertrag - prüfen ob Austrittsdatum aktualisiert werden muss
                if ($endDate && !$existingContract->end_date) {
                    $existingContract->update([
                        'end_date' => $endDate,
                        'is_active' => false,
                    ]);
                    $this->stats['contracts_updated']++;
                }
                return;
            }
            
            // Soll-Stunden aus CSV parsen (Komma zu Punkt konvertieren)
            $sollStunden = null;
            if (!empty($row['soll_stunden'])) {
                $sollStunden = (float) str_replace(',', '.', $row['soll_stunden']);
            }
            
            // Nur Vertrag erstellen wenn start_date vorhanden ist
            if (!$startDate) {
                echo "DEBUG: Skipping contract for {$row['personalnummer']} - no valid start date\n";
                return;
            }
            
            // Neuen Vertrag erstellen
            $contract = HcmEmployeeContract::create([
                'employee_id' => $employee->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'contract_type' => 'unbefristet', // Standard
                'employment_status' => 'aktiv',
                'hours_per_month' => $sollStunden, // Soll-Stunden aus CSV
                'cost_center' => $row['ks_schlüssel'], // Kostenstelle aus CSV (String-Referenz)
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
                    DB::table('hcm_employee_contract_title_links')->insert([
                        'contract_id' => $contract->id,
                        'job_title_id' => $jobTitle->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->stats['contract_title_links_created']++;
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

    private function createCrmContacts($data, $additionalData = [])
    {
        foreach ($data as $row) {
            $this->createCrmContact($row, $additionalData);
        }
    }

    private function createCrmContact($row, $additionalData = [])
    {
        try {
            echo "DEBUG: Creating CRM contact for {$row['vorname']} {$row['nachname']} (Personalnummer: {$row['personalnummer']})\n";
            echo "DEBUG: Email: '{$row['email']}', Telefon: '{$row['telefon']}', Mobil: '{$row['mobil']}'\n";
            
            // Merge additional data if available
            $personalnummer = $row['personalnummer'];
            if (isset($additionalData[$personalnummer])) {
                $additional = $additionalData[$personalnummer];
                echo "DEBUG: Additional data found - Birthdate: '{$additional['birthdate']}', Salutation: '{$additional['salutation']}', Gender: '{$additional['gender']}'\n";
            }
            
            // Mitarbeiter finden
            $employee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->first();

            if (!$employee) {
                echo "DEBUG: No employee found for {$row['personalnummer']} - skipping CRM contact\n";
                return;
            }
            
            echo "DEBUG: Found employee ID {$employee->id} for {$row['personalnummer']}\n";

            // Prüfen ob Mitarbeiter bereits einen verknüpften CRM Kontakt hat
            $existingLink = CrmContactLink::where('linkable_id', $employee->id)
                ->where('linkable_type', HcmEmployee::class)
                ->first();

            if ($existingLink) {
                // Mitarbeiter hat bereits einen CRM Kontakt - Update
                $contact = $existingLink->contact;
                
                // Prepare update data
                $updateData = [
                    'first_name' => $row['vorname'],
                    'last_name' => $row['nachname'],
                ];
                
                // Add additional data if available
                if (isset($additionalData[$personalnummer])) {
                    $additional = $additionalData[$personalnummer];
                    if (!empty($additional['birthdate'])) {
                        $updateData['birth_date'] = $this->parseDateValue($additional['birthdate']);
                    }
                    // Add other fields as needed
                }
                
                $contact->update($updateData);
                echo "DEBUG: Update CRM Kontakt für {$row['vorname']} {$row['nachname']}\n";
                echo "DEBUG: ===========================================\n";
                echo "DEBUG: EMAIL/TELEFON DEBUG START\n";
                echo "DEBUG: ===========================================\n";
                echo "DEBUG: Email: '{$row['email']}', Telefon: '{$row['telefon']}', Mobil: '{$row['mobil']}'\n";
                echo "DEBUG: *** CALLING updateContactData for {$row['vorname']} ***\n";
                
                // Update Email-Adressen und Telefonnummern für bestehende Kontakte
                $this->updateContactData($contact, $row);
                echo "DEBUG: *** FINISHED updateContactData for {$row['vorname']} ***\n";
            } else {
                // Neuer CRM Kontakt erstellen
                $contactData = [
                    'team_id' => $this->teamId,
                    'first_name' => $row['vorname'],
                    'last_name' => $row['nachname'],
                    'is_active' => true,
                    'created_by_user_id' => $this->userId,
                ];
                
                // Add additional data if available
                if (isset($additionalData[$personalnummer])) {
                    $additional = $additionalData[$personalnummer];
                    if (!empty($additional['birthdate'])) {
                        $contactData['birth_date'] = $this->parseDateValue($additional['birthdate']);
                    }
                    // Add other fields as needed
                }
                
                $contact = CrmContact::create($contactData);
                echo "DEBUG: Erstelle CRM Kontakt für {$row['vorname']} {$row['nachname']}\n";
                
                // Initial creation of email/phone/address for new contacts
                if (!empty($row['email']) && trim($row['email']) !== '') {
                    echo "DEBUG: Creating email for new contact {$row['vorname']}: {$row['email']}\n";
                    $this->createEmailAddress($contact, $row['email'], 1, true);
                } else {
                    echo "DEBUG: No email provided for {$row['vorname']}\n";
                }
                if (!empty($row['telefon']) && trim($row['telefon']) !== '') {
                    echo "DEBUG: Creating telefon for new contact {$row['vorname']}: {$row['telefon']}\n";
                    $this->createPhoneNumber($contact, $row['telefon'], 1, true);
                } else {
                    echo "DEBUG: No telefon provided for {$row['vorname']}\n";
                }
                if (!empty($row['mobil']) && trim($row['mobil']) !== '') {
                    echo "DEBUG: Creating mobil for new contact {$row['vorname']}: {$row['mobil']}\n";
                    $this->createPhoneNumber($contact, $row['mobil'], 2, false);
                } else {
                    echo "DEBUG: No mobil provided for {$row['vorname']}\n";
                }
                if (!empty($row['straße']) && !empty($row['plz']) && !empty($row['ort'])) {
                    CrmPostalAddress::create([
                        'addressable_id' => $contact->id,
                        'addressable_type' => CrmContact::class,
                        'street' => trim($row['straße']),
                        'postal_code' => trim($row['plz']),
                        'city' => trim($row['ort']),
                        'additional_info' => trim($row['adresszusatz']),
                        'address_type_id' => 1,
                        'is_primary' => true,
                    ]);
                }
            }

            // Link to employee if exists
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

            if (!$existingLink) {
                $this->stats['crm_contacts_created']++;
            } else {
                $this->stats['crm_contacts_updated']++;
            }
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
                    'contact_status_id' => 1, // Standard Contact Status
                    'is_active' => true,
                    'created_by_user_id' => $this->userId,
                ]);
            }

            // Prüfen ob Relation bereits existiert
            $existingRelation = CrmContactRelation::where('contact_id', $contact->id)
                ->where('company_id', $company->id)
                ->where('relation_type_id', 1)
                ->first();
            
            if (!$existingRelation) {
                // Create contact relation
                CrmContactRelation::create([
                    'contact_id' => $contact->id,
                    'company_id' => $company->id,
                    'relation_type_id' => 1, // Employee relation type
                    'position' => $row['stellenbezeichnung'] ?: $row['tätigkeit'],
                    'start_date' => $this->parseDateValue($row['eintrittsdatum']),
                    'end_date' => $this->parseDateValue($row['austrittsdatum']),
                    'is_primary' => true,
                    'is_active' => empty($row['austrittsdatum']),
                ]);

                $this->stats['crm_company_relations_created']++;
            }
        } catch (\Exception $e) {
            $this->stats['errors'][] = "Company relation {$row['personalnummer']}: " . $e->getMessage();
        }
    }

    private function createPhoneNumber($contact, $phoneInput, $phoneTypeId, $isPrimary)
    {
        try {
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
            
            $contact->phoneNumbers()->create($phoneData);
            
        } catch (NumberParseException $e) {
            // Fallback: Nur raw_input speichern bei Parsing-Fehlern
            $contact->phoneNumbers()->create([
                'raw_input' => $phoneInput,
                'phone_type_id' => $phoneTypeId,
                'is_primary' => $isPrimary,
            ]);
        } catch (\Exception $e) {
            // Fallback: Nur raw_input speichern bei anderen Fehlern
            $contact->phoneNumbers()->create([
                'raw_input' => $phoneInput,
                'phone_type_id' => $phoneTypeId,
                'is_primary' => $isPrimary,
            ]);
        }
    }

    private function updateContactData($contact, $row)
    {
        echo "DEBUG: Updating contact data for {$row['vorname']} {$row['nachname']}\n";
        echo "DEBUG: Email value: '{$row['email']}' (empty: " . (empty($row['email']) ? 'true' : 'false') . ")\n";
        echo "DEBUG: Telefon value: '{$row['telefon']}' (empty: " . (empty($row['telefon']) ? 'true' : 'false') . ")\n";
        echo "DEBUG: Mobil value: '{$row['mobil']}' (empty: " . (empty($row['mobil']) ? 'true' : 'false') . ")\n";
        
        // Update Email-Adressen
        $this->updateEmailAddresses($contact, $row);
        
        // Update Telefonnummern
        $this->updatePhoneNumbers($contact, $row);
        
        // Update Adressen
        $this->updateAddresses($contact, $row);
    }

    private function updateEmailAddresses($contact, $row)
    {
        if (!empty($row['email']) && trim($row['email']) !== '') {
            $emailAddress = trim($row['email']);
            
            // Prüfen ob Email bereits existiert
            $existingEmail = $contact->emailAddresses()
                ->where('email_address', $emailAddress)
                ->first();
            
            if (!$existingEmail) {
                echo "DEBUG: Adding new email for {$row['vorname']}: {$emailAddress}\n";
                $this->createEmailAddress($contact, $emailAddress, 1, true);
            } else {
                echo "DEBUG: Email already exists for {$row['vorname']}: {$emailAddress}\n";
                // Aktiviere die bestehende Email
                $existingEmail->update(['is_active' => true, 'is_primary' => true]);
            }
        } else {
            // Wenn keine Email in CSV, aber Kontakt hat Emails - diese als inaktiv markieren
            $contact->emailAddresses()->update(['is_active' => false]);
            echo "DEBUG: No email in CSV for {$row['vorname']} - marking existing emails as inactive\n";
        }
    }

    private function updatePhoneNumbers($contact, $row)
    {
        $hasPhone = !empty($row['telefon']) && trim($row['telefon']) !== '';
        $hasMobile = !empty($row['mobil']) && trim($row['mobil']) !== '';
        
        // Update Telefon
        if ($hasPhone) {
            $phoneNumber = trim($row['telefon']);
            
            $existingPhone = $contact->phoneNumbers()
                ->where('raw_input', $phoneNumber)
                ->first();
            
            if (!$existingPhone) {
                echo "DEBUG: Adding new telefon for {$row['vorname']}: {$phoneNumber}\n";
                $this->createPhoneNumber($contact, $phoneNumber, 1, true);
            } else {
                echo "DEBUG: Telefon already exists for {$row['vorname']}: {$phoneNumber}\n";
                // Aktiviere die bestehende Telefonnummer
                $existingPhone->update(['is_active' => true, 'is_primary' => true]);
            }
        }
        
        // Update Mobil
        if ($hasMobile) {
            $mobileNumber = trim($row['mobil']);
            
            $existingMobile = $contact->phoneNumbers()
                ->where('raw_input', $mobileNumber)
                ->first();
            
            if (!$existingMobile) {
                echo "DEBUG: Adding new mobil for {$row['vorname']}: {$mobileNumber}\n";
                $this->createPhoneNumber($contact, $mobileNumber, 2, false);
            } else {
                echo "DEBUG: Mobil already exists for {$row['vorname']}: {$mobileNumber}\n";
                // Aktiviere die bestehende Mobilnummer
                $existingMobile->update(['is_active' => true]);
            }
        }
        
        // Wenn keine Telefonnummern in CSV, aber Kontakt hat welche - diese als inaktiv markieren
        if (!$hasPhone && !$hasMobile) {
            $contact->phoneNumbers()->update(['is_active' => false]);
            echo "DEBUG: No phone numbers in CSV for {$row['vorname']} - marking existing phones as inactive\n";
        }
    }

    private function updateAddresses($contact, $row)
    {
        if (!empty($row['straße']) && !empty($row['plz']) && !empty($row['ort'])) {
            $street = trim($row['straße']);
            $postalCode = trim($row['plz']);
            $city = trim($row['ort']);
            
            $existingAddress = $contact->postalAddresses()
                ->where('street', $street)
                ->where('postal_code', $postalCode)
                ->where('city', $city)
                ->first();
            
            if (!$existingAddress) {
                echo "DEBUG: Adding new address for {$row['vorname']}: {$street}, {$postalCode} {$city}\n";
                $contact->postalAddresses()->create([
                    'street' => $street,
                    'postal_code' => $postalCode,
                    'city' => $city,
                    'additional_info' => trim($row['adresszusatz']),
                    'address_type_id' => 1,
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            } else {
                echo "DEBUG: Address already exists for {$row['vorname']}: {$street}, {$postalCode} {$city}\n";
            }
        }
    }

    private function createEmailAddress($contact, $emailInput, $emailTypeId = 1, $isPrimary = false)
    {
        try {
            // Email validieren
            if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
                echo "DEBUG: Invalid email format: {$emailInput}\n";
                return; // Ungültige Email-Adresse
            }
            
            // Wenn als primär markiert, alle anderen als nicht-primär setzen
            if ($isPrimary) {
                CrmEmailAddress::where('emailable_id', $contact->id)
                    ->where('emailable_type', CrmContact::class)
                    ->update(['is_primary' => false]);
            }
            
            $contact->emailAddresses()->create([
                'email_address' => $emailInput,
                'email_type_id' => $emailTypeId,
                'is_primary' => $isPrimary,
                'is_active' => true,
            ]);
            
        } catch (\Exception $e) {
            // Fehler beim Erstellen der Email-Adresse ignorieren
            $this->stats['errors'][] = "Email {$emailInput}: " . $e->getMessage();
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
            // Im Dry Run prüfen wir, ob der Mitarbeiter existiert ODER erstellt werden würde
            $employee = HcmEmployee::where('team_id', $this->teamId)
                ->where('employee_number', $row['personalnummer'])
                ->where('employer_id', $this->employerId)
                ->first();

            // Wenn Mitarbeiter nicht existiert, wird er im Import erstellt
            $employeeWillBeCreated = !$employee;
            
            if ($employee || $employeeWillBeCreated) {
                $startDate = $row['eintrittsdatum'] ? Carbon::createFromFormat('d.m.Y', $row['eintrittsdatum']) : null;
                
                // Prüfen ob Vertrag bereits existiert (nur wenn Mitarbeiter bereits existiert)
                $existingContract = null;
                if ($employee) {
                    $existingContract = HcmEmployeeContract::where('employee_id', $employee->id)->first();
                }
                
                if (!$existingContract) {
                    $this->stats['contracts_created']++;
                    
                    // Count title links
                    if (!empty($row['stellenbezeichnung'])) {
                        $this->stats['contract_title_links_created']++;
                    }
                    
                    // Count activity links
                    if (!empty($row['tätigkeit'])) {
                        $this->stats['contract_activity_links_created']++;
                    }
                }
            }
        }
    }

    private function analyzeCostCenters($data)
    {
        $costCenters = [];
        
        foreach ($data as $row) {
            $ksSchlüssel = trim($row['ks_schlüssel']);
            if (!empty($ksSchlüssel) && !in_array($ksSchlüssel, $costCenters)) {
                $costCenters[] = $ksSchlüssel;
                
                // Prüfe ob Kostenstelle bereits existiert
                $existingCostCenter = OrganizationCostCenter::where('team_id', $this->teamId)
                    ->where('code', $ksSchlüssel)
                    ->first();
                
                if (!$existingCostCenter) {
                    $this->stats['cost_centers_created']++;
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

    private function testDatabaseOperations($data)
    {
        // Test mit einer kleinen Stichprobe der Daten
        $sampleData = array_slice($data, 0, 3); // Nur die ersten 3 Einträge testen
        
        try {
            DB::transaction(function () use ($sampleData) {
                // Test Job Titles
                foreach ($sampleData as $row) {
                    if (!empty($row['stellenbezeichnung'])) {
                        $jobTitle = new HcmJobTitle();
                        $jobTitle->team_id = $this->teamId;
                        $jobTitle->code = 'TEST_' . substr(md5($row['stellenbezeichnung']), 0, 8);
                        $jobTitle->name = $row['stellenbezeichnung'];
                        $jobTitle->is_active = true;
                        $jobTitle->created_by_user_id = $this->userId;
                        $jobTitle->save();
                    }
                }
                
                // Test Job Activities
                foreach ($sampleData as $row) {
                    if (!empty($row['tätigkeit'])) {
                        // Prüfe ob bereits existiert
                        $existing = HcmJobActivity::where('team_id', $this->teamId)
                            ->where('name', $row['tätigkeit'])
                            ->first();
                        
                        if (!$existing) {
                            $jobActivity = new HcmJobActivity();
                            $jobActivity->team_id = $this->teamId;
                            $jobActivity->code = 'TEST_' . substr(md5($row['tätigkeit'] . time()), 0, 8);
                            $jobActivity->name = $row['tätigkeit'];
                            $jobActivity->is_active = true;
                            $jobActivity->created_by_user_id = $this->userId;
                            $jobActivity->save();
                        }
                    }
                }
                
                // Test Employees
                foreach ($sampleData as $row) {
                    $employee = new HcmEmployee();
                    $employee->team_id = $this->teamId;
                    $employee->employee_number = $row['personalnummer'];
                    $employee->employer_id = $this->employerId;
                    $employee->is_active = empty($row['austrittsdatum']);
                    $employee->created_by_user_id = $this->userId;
                    $employee->save();
                }
                
                // Test CRM Contacts
                foreach ($sampleData as $row) {
                    $contact = new CrmContact();
                    $contact->team_id = $this->teamId;
                    $contact->first_name = $row['vorname'];
                    $contact->last_name = $row['nachname'];
                    $contact->is_active = true;
                    $contact->created_by_user_id = $this->userId;
                    $contact->save();
                }
                
                // Test CRM Company
                $employer = HcmEmployer::find($this->employerId);
                if ($employer) {
                    $company = new CrmCompany();
                    $company->team_id = $this->teamId;
                    $company->name = $employer->display_name;
                    $company->contact_status_id = 1; // Test mit Standard Status
                    $company->is_active = true;
                    $company->created_by_user_id = $this->userId;
                    $company->save();
                }
                
                // Rollback - alle Änderungen werden rückgängig gemacht
                throw new \Exception('DRY_RUN_ROLLBACK');
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === 'DRY_RUN_ROLLBACK') {
                // Das ist der erwartete Rollback - alles OK
                return;
            }
            
            // Echter Fehler - weiterleiten
            throw $e;
        }
    }
}
