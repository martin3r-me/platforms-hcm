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
            'crmContactLinks.contact.postalAddresses' => function ($q) {
                $q->where('is_active', true)->with('country'); // Nur aktive Adressen mit Country
            },
            'crmContactLinks.contact.gender',
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
        
        // Email-Adressen: Primäre bevorzugen (unabhängig vom Typ), sonst erste
        $emailAddresses = $employee->getEmailAddresses();
        $primaryEmail = collect($emailAddresses)->firstWhere('is_primary') 
            ?? collect($emailAddresses)->first();
        
        // Telefonnummern: Primäre bevorzugen (unabhängig vom Typ), sonst erste
        $phoneNumbers = $employee->getPhoneNumbers();
        
        // Primäre Telefonnummer (unabhängig vom Typ)
        $primaryPhone = collect($phoneNumbers)->firstWhere('is_primary')
            ?? collect($phoneNumbers)->first();
        
        // Prüfe ob primäre Telefonnummer Landline oder Mobile ist
        $phoneLandline = null;
        $phoneMobile = null;
        
        if ($primaryPhone) {
            $phoneType = strtolower($primaryPhone['type'] ?? '');
            if (in_array($phoneType, ['landline', 'festnetz', 'telefon'])) {
                $phoneLandline = $primaryPhone;
            } elseif (in_array($phoneType, ['mobile', 'handy', 'mobil'])) {
                $phoneMobile = $primaryPhone;
            }
        }
        
        // Falls keine primäre gefunden oder Typ nicht erkannt, nach Typ suchen
        if (!$phoneLandline) {
            $phoneLandline = collect($phoneNumbers)
                ->filter(function ($phone) {
                    return in_array(strtolower($phone['type'] ?? ''), ['landline', 'festnetz', 'telefon']);
                })
                ->first();
        }
        
        if (!$phoneMobile) {
            $phoneMobile = collect($phoneNumbers)
                ->filter(function ($phone) {
                    return in_array(strtolower($phone['type'] ?? ''), ['mobile', 'handy', 'mobil']);
                })
                ->first();
        }
        
        // Adressen - direkt vom Contact holen für besseren Zugriff auf Country-Code
        // Nur aktive Adressen verwenden
        $postalAddresses = $contact?->postalAddresses 
            ? $contact->postalAddresses->where('is_active', true) 
            : collect();
        $primaryAddress = $postalAddresses->firstWhere('is_primary') 
            ?? $postalAddresses->first();
        
        // Initials aus Vor- und Nachname
        $initials = $this->extractInitials(
            $contact?->first_name ?? '',
            $contact?->last_name ?? ''
        );
        
        // Gender mapping - Priorität: Contact Gender > Employee Gender
        $genderValue = $contact?->gender?->code 
            ?? $contact?->gender?->name 
            ?? $employee->gender;
        $gender = $this->mapGender($genderValue);
        
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

        // Stammkostenstelle aus aktuellem Contract
        $costCenter = null;
        if ($activeContract) {
            $costCenterObj = $activeContract->getCostCenter();
            if ($costCenterObj) {
                $costCenter = $costCenterObj->code ?? $costCenterObj->name;
            } else {
                // Fallback auf cost_center String-Feld
                $costCenter = $activeContract->cost_center;
            }
        }

        return [
            // Employee Profile
            'employee_number' => $employee->employee_number ?? '',
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
            'street' => $primaryAddress?->street ?? '',
            'house_number' => $primaryAddress?->house_number ?? '',
            'house_number_addition' => null, // Wird nicht aus CRM geholt
            'postal_code' => $primaryAddress?->postal_code ?? '',
            'city' => $primaryAddress?->city ?? '',
            'country' => $primaryAddress?->country?->code ?? null, // Verwende Country-Code statt Name, kein Fallback
            
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
            'cost_center' => $costCenter,
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
        
        $genderLower = strtolower(trim($gender));
        
        // Gender Codes (MALE, FEMALE, DIVERSE, NOT_SPECIFIED)
        if (in_array($genderLower, ['male', 'männlich'])) {
            return 'Male';
        }
        
        if (in_array($genderLower, ['female', 'weiblich'])) {
            return 'Female';
        }
        
        // Abkürzungen
        if (in_array($genderLower, ['m', 'mann', 'herr'])) {
            return 'Male';
        }
        
        if (in_array($genderLower, ['f', 'w', 'frau'])) {
            return 'Female';
        }
        
        // Diverse/Not Specified werden als Male gemappt (Nostradamus erwartet nur Male/Female)
        if (in_array($genderLower, ['diverse', 'divers', 'd', 'not_specified', 'nicht angegeben', 'x unbestimmt', 'unbestimmt'])) {
            return 'Male'; // Fallback für Diverse/Not Specified
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

