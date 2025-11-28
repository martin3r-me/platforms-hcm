<?php

namespace Platform\Hcm\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;
use Illuminate\Http\Request;

/**
 * Nostradamus API Controller für Employees
 * 
 * Exklusiver Endpunkt für Nostradamus - gibt flache, aufbereitete Daten zurück
 */
class EmployeeNostradamusController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Employees (Nostradamus)
     * 
     * Gibt flache, aufbereitete Daten zurück mit allen CRM- und Contract-Informationen
     */
    public function index(Request $request)
    {
        // Employer UUID aus Request
        $employerUuid = $request->get('employer_uuid');
        
        if (!$employerUuid) {
            return $this->error('employer_uuid ist erforderlich', null, 400);
        }

        // Finde Employer
        $employer = HcmEmployer::where('uuid', $employerUuid)->first();
        
        if (!$employer) {
            return $this->error('Employer nicht gefunden', null, 404);
        }

        // Query für Employees
        $query = HcmEmployee::with([
            'crmContactLinks.contact.emailAddresses',
            'crmContactLinks.contact.phoneNumbers',
            'crmContactLinks.contact.postalAddresses',
            'contracts' => function ($q) {
                $q->orderBy('start_date', 'desc');
            },
            'contracts.jobActivities',
            'contracts.jobTitles',
        ])
        ->where('employer_id', $employer->id)
        ->where('is_active', true);

        // Sorting
        $sortBy = $request->get('sort_by', 'employee_number');
        $sortDir = $request->get('sort_dir', 'asc');
        
        $allowedSortColumns = ['id', 'employee_number', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('employee_number', 'asc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 100), 1000);
        $employees = $query->paginate($perPage);

        // Formatting - Flache Daten für Nostradamus
        $formatted = $employees->map(function ($employee) {
            return $this->formatEmployeeForNostradamus($employee);
        });

        return $this->paginated(
            $employees->setCollection($formatted),
            'Employees erfolgreich geladen'
        );
    }

    /**
     * Formatiert einen Employee für Nostradamus (flache Struktur)
     */
    protected function formatEmployeeForNostradamus(HcmEmployee $employee): array
    {
        $contact = $employee->getContact();
        $activeContract = $employee->activeContract();
        
        // Email-Adressen
        $emailAddresses = $employee->getEmailAddresses();
        $primaryEmail = collect($emailAddresses)->firstWhere('is_primary') 
            ?? collect($emailAddresses)->first();
        
        // Telefonnummern
        $phoneNumbers = $employee->getPhoneNumbers();
        $phoneLandline = collect($phoneNumbers)->first(function ($phone) {
            return in_array(strtolower($phone['type'] ?? ''), ['landline', 'festnetz', 'telefon']);
        });
        $phoneMobile = collect($phoneNumbers)->first(function ($phone) {
            return in_array(strtolower($phone['type'] ?? ''), ['mobile', 'handy', 'mobil']);
        });
        
        // Adressen
        $addresses = $employee->getPostalAddresses();
        $primaryAddress = collect($addresses)->firstWhere('is_primary') 
            ?? collect($addresses)->first();
        
        // Initials aus Vor- und Nachname
        $initials = $this->extractInitials(
            $contact?->first_name ?? '',
            $contact?->last_name ?? ''
        );
        
        // Gender mapping
        $gender = $this->mapGender($employee->gender);
        
        // Job Code aus aktuellem Vertrag
        $jobCode = null;
        if ($activeContract) {
            // Priorität: primaryJobActivity > jobActivities (erste) > jobTitles (erste)
            if ($activeContract->primaryJobActivity) {
                $jobCode = $activeContract->primaryJobActivity->code;
            } elseif ($activeContract->jobActivities->isNotEmpty()) {
                $jobCode = $activeContract->jobActivities->first()?->code;
            } elseif ($activeContract->jobTitles->isNotEmpty()) {
                $jobCode = $activeContract->jobTitles->first()?->code;
            }
        }
        
        // Employment Start/End Date
        // Nehme das erste und letzte Contract-Datum
        $contracts = $employee->contracts()->orderBy('start_date', 'asc')->get();
        $employmentStartDate = $contracts->first()?->start_date?->format('Y-m-d');
        $employmentEndDate = null;
        
        // Wenn kein aktiver Vertrag und letzter Vertrag beendet, dann employment_end_date
        if (!$activeContract && $contracts->isNotEmpty()) {
            $lastContract = $contracts->last();
            if ($lastContract->end_date) {
                $employmentEndDate = $lastContract->end_date->format('Y-m-d');
            }
        }
        
        // Contract Hours per Week
        $contractHoursPerWeek = $activeContract?->hours_per_week ?? 0;
        
        // Employee Profile Code (kann aus Contract oder anderen Feldern kommen)
        $employeeProfileCode = $activeContract?->employment_relationship_id 
            ?? $activeContract?->contract_type
            ?? null;

        return [
            // Employee Profile
            'initials' => $initials,
            'first_name' => $contact?->first_name,
            'last_name' => $contact?->last_name ?? '',
            'full_first_names' => $contact?->first_name,
            'date_of_birth' => $employee->birth_date?->format('Y-m-d'),
            'gender' => $gender,
            'email' => $primaryEmail['email'] ?? '',
            'phone_landline' => $phoneLandline['number'] ?? null,
            'phone_mobile' => $phoneMobile['number'] ?? null,
            
            // Address
            'street' => $primaryAddress['street'] ?? '',
            'house_number' => $primaryAddress['house_number'] ?? '',
            'house_number_addition' => null, // Wird nicht aus CRM geholt
            'postal_code' => $primaryAddress['postal_code'] ?? '',
            'city' => $primaryAddress['city'] ?? '',
            'country' => $primaryAddress['country'] ?? 'NL',
            
            // Job
            'job_code' => (string) $jobCode ?? '',
            
            // Employment
            'employment_start_date' => $employmentStartDate,
            'employment_end_date' => $employmentEndDate,
            
            // Contract
            'contract_start_date' => $activeContract?->start_date?->format('Y-m-d'),
            'contract_end_date' => $activeContract?->end_date?->format('Y-m-d'),
            'contract_hours_per_week' => $contractHoursPerWeek,
            'employee_profile_code' => $employeeProfileCode,
        ];
    }

    /**
     * Extrahiert Initials aus Vor- und Nachname
     */
    protected function extractInitials(?string $firstName, ?string $lastName): string
    {
        $initials = '';
        
        if ($firstName) {
            $parts = explode(' ', $firstName);
            foreach ($parts as $part) {
                if (!empty($part)) {
                    $initials .= strtoupper(substr($part, 0, 1)) . '.';
                }
            }
        }
        
        if ($lastName) {
            $initials .= ' ' . strtoupper(substr($lastName, 0, 1)) . '.';
        }
        
        return trim($initials);
    }

    /**
     * Mappt Gender-Werte
     */
    protected function mapGender(?string $gender): string
    {
        if (!$gender) {
            return 'Male'; // Default
        }
        
        $gender = strtolower($gender);
        
        if (in_array($gender, ['male', 'm', 'mann', 'männlich'])) {
            return 'Male';
        }
        
        if (in_array($gender, ['female', 'f', 'frau', 'weiblich'])) {
            return 'Female';
        }
        
        return 'Male'; // Default fallback
    }

    /**
     * Health Check Endpoint
     */
    public function health(Request $request)
    {
        try {
            $employerUuid = $request->get('employer_uuid');
            
            if (!$employerUuid) {
                return $this->success([
                    'status' => 'ok',
                    'message' => 'API ist erreichbar, aber employer_uuid fehlt',
                    'timestamp' => now()->toIso8601String(),
                ], 'Health Check');
            }

            $employer = HcmEmployer::where('uuid', $employerUuid)->first();
            
            if (!$employer) {
                return $this->success([
                    'status' => 'ok',
                    'message' => 'API ist erreichbar, aber Employer nicht gefunden',
                    'employer_uuid' => $employerUuid,
                    'timestamp' => now()->toIso8601String(),
                ], 'Health Check');
            }

            $employeeCount = HcmEmployee::where('employer_id', $employer->id)
                ->where('is_active', true)
                ->count();

            return $this->success([
                'status' => 'ok',
                'message' => 'API ist erreichbar',
                'employer_uuid' => $employerUuid,
                'employer_id' => $employer->id,
                'active_employees' => $employeeCount,
                'timestamp' => now()->toIso8601String(),
            ], 'Health Check');

        } catch (\Exception $e) {
            return $this->error('Health Check fehlgeschlagen: ' . $e->getMessage(), null, 500);
        }
    }
}

