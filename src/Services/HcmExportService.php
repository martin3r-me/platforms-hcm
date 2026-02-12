<?php

namespace Platform\Hcm\Services;

use Carbon\Carbon;
use Platform\Hcm\Models\HcmExport;
use Platform\Hcm\Models\HcmExportTemplate;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Models\HcmContractTimeRecord;
use Platform\Hcm\Models\HcmContractAbsenceDay;
use Platform\Hcm\Models\HcmContractVacationDay;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class HcmExportService
{
    public function __construct(
        private int $teamId,
        private int $userId
    ) {}

    /**
     * Erstellt einen neuen Export
     */
    public function createExport(
        string $name,
        string $type,
        string $format = 'csv',
        ?int $templateId = null,
        ?array $parameters = null
    ): HcmExport {
        return HcmExport::create([
            'team_id' => $this->teamId,
            'created_by_user_id' => $this->userId,
            'name' => $name,
            'type' => $type,
            'format' => $format,
            'export_template_id' => $templateId,
            'parameters' => $parameters,
            'status' => 'pending',
        ]);
    }

    /**
     * Führt einen Export aus (flexible Basis-Methode)
     */
    public function executeExport(HcmExport $export): string
    {
        $export->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            // Basierend auf Export-Typ die entsprechende Methode aufrufen
            $filepath = match($export->type) {
                'infoniqa-ma' => $this->exportInfoniqa($export),
                'infoniqa-dimensions' => $this->exportInfoniqaDimensions($export),
                'infoniqa-bank' => $this->exportInfoniqaBank($export),
                'infoniqa-zeitwirtschaft' => $this->exportInfoniqaZeitwirtschaft($export),
                'infoniqa-zeitwirtschaft-monat' => $this->exportInfoniqaZeitwirtschaftMonat($export),
                'payroll' => $this->exportPayroll($export),
                'employees' => $this->exportEmployees($export),
                'custom' => $this->exportCustom($export),
                default => throw new \InvalidArgumentException("Unbekannter Export-Typ: {$export->type}"),
            };

            $disk = Storage::disk('local')->exists($filepath)
                ? 'local'
                : (Storage::disk('public')->exists($filepath) ? 'public' : 'local');

            $fileSize = Storage::disk($disk)->size($filepath);
            $recordCount = $this->getRecordCount($export, $filepath, $disk);

            $export->update([
                'status' => 'completed',
                'completed_at' => now(),
                'file_path' => $filepath,
                'file_name' => basename($filepath),
                'file_size' => $fileSize,
                'record_count' => $recordCount,
            ]);

            return $filepath;
        } catch (\Throwable $e) {
            // Detaillierte Fehlermeldung mit Stack-Trace für Debugging
            $errorMessage = $e->getMessage() . "\n\n" . 
                          "File: " . $e->getFile() . ":" . $e->getLine() . "\n" .
                          "Trace:\n" . $e->getTraceAsString();
            
            $export->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);
            
            // Für Logging
            \Log::error('Export fehlgeschlagen', [
                'export_id' => $export->id,
                'type' => $export->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * INFONIQA-Export (komplexes Format mit mehreren Header-Zeilen)
     */
    private function exportInfoniqa(HcmExport $export): string
    {
        $filename = 'infoniqa_ma_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        // employer_id aus Parameters holen
        $parameters = $export->parameters ?? [];
        $employerId = $parameters['employer_id'] ?? null;
        
        if (!$employerId) {
            throw new \InvalidArgumentException('INFONIQA MA-Export benötigt employer_id in den Parametern');
        }

        $employer = HcmEmployer::findOrFail($employerId);
        if ($employer->team_id !== $this->teamId) {
            throw new \InvalidArgumentException('Arbeitgeber gehört nicht zum aktuellen Team');
        }

        // Lade Mitarbeiter mit allen benötigten Relationen
        $employees = \Platform\Hcm\Models\HcmEmployee::with([
            'employer',
            'churchTaxType',
            'crmContactLinks.contact.postalAddresses',
            'crmContactLinks.contact.emailAddresses.emailType',
            'crmContactLinks.contact.phoneNumbers.phoneType',
            'crmContactLinks.contact.gender',
            'crmContactLinks.contact.salutation',
            'contracts' => function($q) {
                $q->where('is_active', true)
                  ->orderBy('start_date', 'desc');
            },
            'contracts.tariffGroup.tariffAgreement',
            'contracts.tariffLevel',
            'contracts.insuranceStatus',
            'contracts.pensionType',
            'contracts.employmentRelationship',
            'contracts.personGroup',
            'contracts.primaryJobActivity',
            'contracts.jobActivityAlias',
            'contracts.jobTitles',
            'contracts.taxClass',
            'contracts.costCenterLinks.costCenter',
            'contracts.levyTypes',
            'healthInsuranceCompany',
            'payoutMethod',
            'benefits' => function($q) {
                $q->where('benefit_type', 'bkv')
                  ->where('is_active', true);
            },
        ])
        ->where('team_id', $this->teamId)
        ->where('employer_id', $employerId)
        ->where('is_active', true)
        ->get();

        $csvData = $this->generateInfoniqaCsv($employees, $employer);

        // UTF-8 BOM für Excel-Kompatibilität
        $csvData = "\xEF\xBB\xBF" . $csvData;
        
        // Exports in geschütztem Storage-Verzeichnis speichern (nicht public)
        Storage::disk('local')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * INFONIQA Zeitwirtschaft-Export
     */
    private function exportInfoniqaZeitwirtschaft(HcmExport $export): string
    {
        $filename = 'infoniqa_zeitwirtschaft_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        $parameters = $export->parameters ?? [];
        $employerId = $parameters['employer_id'] ?? null;

        if (!$employerId) {
            throw new \InvalidArgumentException('INFONIQA Zeitwirtschaft Export benötigt employer_id in den Parametern');
        }

        $employer = HcmEmployer::findOrFail($employerId);
        if ($employer->team_id !== $this->teamId) {
            throw new \InvalidArgumentException('Arbeitgeber gehört nicht zum aktuellen Team');
        }

        // Zeitraum berechnen basierend auf aktuellem Datum
        // Ab dem 15. eines Monats: Abrechnungszeitraum für aktuellen Monat
        // Vor dem 15.: Abrechnungszeitraum für letzten Monat
        $now = Carbon::now();
        
        if ($now->day >= 15) {
            // Ab dem 15.: Abrechnungszeitraum für aktuellen Monat
            // Monat: aktueller Monat (z.B. "01.2026")
            // Start: 15. des letzten Monats (z.B. 15.12.2025)
            // Ende: 14. des aktuellen Monats (z.B. 14.01.2026)
            $lastMonth = $now->copy()->subMonth();
            $monthDisplay = $now->format('m.Y');
            $fromDate = Carbon::create($lastMonth->year, $lastMonth->month, 15);
            $toDate = Carbon::create($now->year, $now->month, 14);
        } else {
            // Vor dem 15.: Abrechnungszeitraum für letzten Monat
            // Monat: letzter Monat (z.B. "12.2025")
            // Start: 15. des vorletzten Monats (z.B. 15.11.2025)
            // Ende: 14. des letzten Monats (z.B. 14.12.2025)
            $lastMonth = $now->copy()->subMonth();
            $monthBeforeLast = $lastMonth->copy()->subMonth();
            $monthDisplay = $lastMonth->format('m.Y');
            $fromDate = Carbon::create($monthBeforeLast->year, $monthBeforeLast->month, 15);
            $toDate = Carbon::create($lastMonth->year, $lastMonth->month, 14);
        }

        // Headlines definieren
        $headers = [
            'Monat',
            'Von Datum (F:',
            'Bis Datum (FZ)',
            'Vorname',
            'Name',
            'MA Code ZW',
            'LA Code ZW',
            'Einheiten (Std.)',
            'Tage',
            'Satz',
            'Betrag',
            'Prozent',
            'Fehlzeitencode',
            'Kostenstelle',
            'Kostenträger',
            'Tarifart',
            'Tarifgruppe',
            'Tarifstufe',
            'zu löschen',
            'Beschreibung init Text',
        ];

        $lines = [];
        $lines[] = $this->escapeCsvRow($headers);

        // Lade alle Mitarbeiter mit aktiven Verträgen (Stundenlohn)
        $employees = HcmEmployee::with([
                'crmContactLinks.contact',
                'contracts' => function ($query) {
                    $query->where('is_active', true)
                          ->where('wage_base_type', 'Stundenlohn');
                },
                'contracts.costCenterLinks.costCenter',
                'contracts.tariffGroup.tariffAgreement',
                'contracts.tariffLevel',
            ])
            ->where('team_id', $this->teamId)
            ->where('employer_id', $employerId)
            ->where('is_active', true)
            ->get();

        // Für jeden Vertrag mit Stundenlohn eine Zeile erstellen
        foreach ($employees as $employee) {
            $contact = $employee->crmContactLinks->first()?->contact;
            $firstName = $contact?->first_name ?? '';
            $lastName = $contact?->last_name ?? '';
            $employeeNumber = (string)($employee->employee_number ?? '');

            foreach ($employee->contracts as $contract) {
                // Kostenstelle vom Vertrag holen
                $costCenter = $contract->getCostCenter();
                $costCenterCode = $costCenter?->code ?? $contract->cost_center ?? '';

                // Tarif-Informationen
                $tariffType = $contract->tariffGroup?->tariffAgreement?->name ?? '';
                $tariffGroup = $contract->tariffGroup?->code ?? '';
                $tariffLevel = $this->formatTariffLevel($contract->tariffLevel);

                // Zeiterfassungsdaten für den Zeitraum abfragen
                $timeRecords = HcmContractTimeRecord::where('contract_id', $contract->id)
                    ->whereBetween('record_date', [$fromDate->toDateString(), $toDate->toDateString()])
                    ->whereNotNull('work_minutes')
                    ->get();

                // Anzahl der Tage (eindeutige Datumsangaben)
                $daysCount = $timeRecords->pluck('record_date')->unique()->count();

                // Gesamtstunden berechnen (work_minutes in Stunden umrechnen)
                $totalMinutes = $timeRecords->sum('work_minutes');
                $totalHours = $totalMinutes > 0 ? round($totalMinutes / 60, 2) : 0;
                
                // Nur Zeile erstellen, wenn Stunden angefallen sind
                if ($totalHours > 0) {
                    // Stundenformatierung mit Komma statt Punkt
                    $totalHoursFormatted = str_replace('.', ',', number_format($totalHours, 2, '.', ''));

                    // Zeile erstellen
                    $row = [
                        $monthDisplay,                                    // Monat
                        $fromDate->format('d.m.Y'),                       // Von Datum (F:
                        $toDate->format('d.m.Y'),                         // Bis Datum (FZ)
                        $firstName,                                       // Vorname
                        $lastName,                                        // Name
                        $employeeNumber,                                  // MA Code ZW
                        '',                                               // LA Code ZW
                        $totalHoursFormatted,                             // Einheiten (Std.) - mit Komma
                        $daysCount > 0 ? (string)$daysCount : '',        // Tage
                        '',                                               // Satz
                        '',                                               // Betrag
                        '',                                               // Prozent
                        '',                                               // Fehlzeitencode
                        $costCenterCode,                                  // Kostenstelle
                        '',                                               // Kostenträger
                        $tariffType,                                      // Tarifart
                        $tariffGroup,                                     // Tarifgruppe
                        $tariffLevel,                                     // Tarifstufe
                        '',                                               // zu löschen
                        '',                                               // Beschreibung init Text
                    ];
                    
                    $lines[] = $this->escapeCsvRow($row);
                }
            }
        }

        // Abwesenheitstage für den Zielmonat hinzufügen (kompletter Monat, inkl. zukünftiger Tage)
        // Zielmonat = Monat des Abrechnungszeitraums-Endes ($toDate) / $monthDisplay
        $absenceFromDate = $toDate->copy()->startOfMonth();
        $absenceToDate = $toDate->copy()->endOfMonth();
        
        $absenceDays = HcmContractAbsenceDay::with([
                'contract.employee.crmContactLinks.contact',
                'contract.costCenterLinks.costCenter',
                'contract.tariffGroup.tariffAgreement',
                'contract.tariffLevel',
                'absenceReason'
            ])
            ->where('team_id', $this->teamId)
            ->whereHas('contract.employee', function ($query) use ($employerId) {
                // WICHTIG: Auch inaktive Mitarbeiter berücksichtigen (Payroll/Export-Logik)
                $query->where('employer_id', $employerId);
            })
            ->whereBetween('absence_date', [$absenceFromDate->toDateString(), $absenceToDate->toDateString()])
            ->orderBy('absence_date')
            ->get();

        // Gruppiere zuerst nach Person (Employee/Vertrag), dann nach Grund
        $absenceByEmployee = $absenceDays->groupBy('employee_id');
        
        foreach ($absenceByEmployee as $employeeId => $employeeAbsences) {
            // Für jede Person: Gruppiere nach Vertrag und Grund
            $absenceByContractAndReason = $employeeAbsences->groupBy(function ($absence) {
                return $absence->contract_id . '_' . ($absence->absence_reason_id ?? 'null');
            });
            
            foreach ($absenceByContractAndReason as $group) {
                $firstAbsence = $group->first();
                $contract = $firstAbsence->contract;
                if (!$contract) {
                    continue;
                }
                $employee = $contract->employee;
                if (!$employee) {
                    continue;
                }
                $contact = $employee->crmContactLinks->first()?->contact;
                
                $firstName = $contact?->first_name ?? '';
                $lastName = $contact?->last_name ?? '';
                $employeeNumber = (string)($employee->employee_number ?? '');
                
                // Kostenstelle vom Vertrag holen
                $costCenter = $contract->getCostCenter();
                $costCenterCode = $costCenter?->code ?? $contract->cost_center ?? '';
                
                // Sortiere nach Datum
                $sortedAbsences = $group->sortBy('absence_date')->values();
                
                // Konsolidiere: Wenn mehrere Tage am Stück mit gleichem Grund, dann zusammenfassen
                $consolidated = [];
                $currentStart = null;
                $currentEnd = null;
                $currentStartAbsence = null;
                $currentHoursSum = 0.0;
                $currentHoursHasValue = false;
                
                foreach ($sortedAbsences as $absence) {
                    $absenceDate = Carbon::parse($absence->absence_date);
                    $dayHours = $absence->absence_hours;
                    if ($dayHours !== null && $dayHours !== '') {
                        $currentHoursSum += (float) $dayHours;
                        $currentHoursHasValue = true;
                    }
                    
                    if ($currentStart === null) {
                        // Erster Tag einer Sequenz
                        $currentStart = $absenceDate;
                        $currentEnd = $absenceDate;
                        $currentStartAbsence = $absence;
                    } elseif ($absenceDate->diffInDays($currentEnd) === 1) {
                        // Fortsetzung der Sequenz (nächster Tag) - gleicher Grund bereits durch Gruppierung sichergestellt
                        $currentEnd = $absenceDate;
                    } else {
                        // Unterbrechung: Speichere aktuelle Sequenz und starte neue
                        $consolidated[] = [
                            'start' => $currentStart,
                            'end' => $currentEnd,
                            'days' => $currentStart->diffInDays($currentEnd) + 1,
                            'hours' => $currentHoursHasValue ? round($currentHoursSum, 2) : null,
                            'reason' => $currentStartAbsence->absenceReason?->code ?? '',
                        ];
                        $currentStart = $absenceDate;
                        $currentEnd = $absenceDate;
                        $currentStartAbsence = $absence;
                        $currentHoursSum = 0.0;
                        $currentHoursHasValue = false;
                        $dayHours = $absence->absence_hours;
                        if ($dayHours !== null && $dayHours !== '') {
                            $currentHoursSum += (float) $dayHours;
                            $currentHoursHasValue = true;
                        }
                    }
                }
                
                // Letzte Sequenz hinzufügen
                if ($currentStart !== null) {
                    $consolidated[] = [
                        'start' => $currentStart,
                        'end' => $currentEnd,
                        'days' => $currentStart->diffInDays($currentEnd) + 1,
                        'hours' => $currentHoursHasValue ? round($currentHoursSum, 2) : null,
                        'reason' => $currentStartAbsence->absenceReason?->code ?? '',
                    ];
                }
                
                // Erstelle Zeilen für konsolidierte Abwesenheiten
                foreach ($consolidated as $consolidation) {
                    $absenceHoursFormatted = '';
                    if (!empty($consolidation['hours']) && (float) $consolidation['hours'] > 0) {
                        $absenceHoursFormatted = str_replace('.', ',', number_format((float) $consolidation['hours'], 2, '.', ''));
                    }
                    $row = [
                        $monthDisplay,                                    // Monat
                        $consolidation['start']->format('d.m.Y'),        // Von Datum (F:
                        $consolidation['end']->format('d.m.Y'),          // Bis Datum (FZ)
                        $firstName,                                       // Vorname
                        $lastName,                                        // Name
                        $employeeNumber,                                  // MA Code ZW
                        '',                                               // LA Code ZW
                        $absenceHoursFormatted,                            // Einheiten (Std.)
                        (string)$consolidation['days'],                   // Tage
                        '',                                               // Satz
                        '',                                               // Betrag
                        '',                                               // Prozent
                        $consolidation['reason'],                         // Fehlzeitencode (Grund der ersten Abwesenheit)
                        $costCenterCode,                                  // Kostenstelle
                        '',                                               // Kostenträger
                        '',                                               // Tarifart (leer für Abwesenheiten)
                        '',                                               // Tarifgruppe (leer für Abwesenheiten)
                        '',                                               // Tarifstufe (leer für Abwesenheiten)
                        '',                                               // zu löschen
                        '',                                               // Beschreibung init Text
                    ];
                    
                    $lines[] = $this->escapeCsvRow($row);
                }
            }
        }

        // Urlaubstage für den Zielmonat hinzufügen (kompletter Monat, inkl. zukünftiger Tage)
        $vacationDays = HcmContractVacationDay::with([
                'contract.employee.crmContactLinks.contact',
                'contract.costCenterLinks.costCenter',
                'contract.tariffGroup.tariffAgreement',
                'contract.tariffLevel',
            ])
            ->where('team_id', $this->teamId)
            ->whereHas('contract.employee', function ($query) use ($employerId) {
                // WICHTIG: Auch inaktive Mitarbeiter berücksichtigen (Payroll/Export-Logik)
                $query->where('employer_id', $employerId);
            })
            ->whereBetween('vacation_date', [$absenceFromDate->toDateString(), $absenceToDate->toDateString()])
            ->orderBy('vacation_date')
            ->get();

        // Gruppiere zuerst nach Person (Employee/Vertrag)
        $vacationByEmployee = $vacationDays->groupBy('employee_id');
        
        foreach ($vacationByEmployee as $employeeId => $employeeVacations) {
            // Für jede Person: Gruppiere nach Vertrag
            $vacationByContract = $employeeVacations->groupBy('contract_id');
            
            foreach ($vacationByContract as $group) {
                $firstVacation = $group->first();
                $contract = $firstVacation->contract;
                if (!$contract) {
                    continue;
                }
                $employee = $contract->employee;
                if (!$employee) {
                    continue;
                }
                $contact = $employee->crmContactLinks->first()?->contact;

                $firstName = $contact?->first_name ?? '';
                $lastName = $contact?->last_name ?? '';
                $employeeNumber = (string)($employee->employee_number ?? '');

                // Kostenstelle vom Vertrag holen
                $costCenter = $contract->getCostCenter();
                $costCenterCode = $costCenter?->code ?? $contract->cost_center ?? '';

                // WICHTIG: Urlaub nicht konsolidieren / nicht summieren -> 1 Zeile pro Tag
                foreach ($group->sortBy('vacation_date')->values() as $vacation) {
                    $vacationDate = Carbon::parse($vacation->vacation_date);

                    $vacationHoursFormatted = '';
                    if ($vacation->vacation_hours !== null && $vacation->vacation_hours !== '' && (float) $vacation->vacation_hours > 0) {
                        $vacationHoursFormatted = str_replace('.', ',', number_format((float) $vacation->vacation_hours, 2, '.', ''));
                    }

                    $row = [
                        $monthDisplay,                                    // Monat
                        $vacationDate->format('d.m.Y'),                   // Von Datum (F:
                        $vacationDate->format('d.m.Y'),                   // Bis Datum (FZ)
                        $firstName,                                       // Vorname
                        $lastName,                                        // Name
                        $employeeNumber,                                  // MA Code ZW
                        '',                                               // LA Code ZW
                        $vacationHoursFormatted,                          // Einheiten (Std.)
                        '1',                                              // Tage (pro Datensatz)
                        '',                                               // Satz
                        '',                                               // Betrag
                        '',                                               // Prozent
                        'URLAUB',                                         // Fehlzeitencode
                        $costCenterCode,                                  // Kostenstelle
                        '',                                               // Kostenträger
                        '',                                               // Tarifart (leer für Urlaub)
                        '',                                               // Tarifgruppe (leer für Urlaub)
                        '',                                               // Tarifstufe (leer für Urlaub)
                        '',                                               // zu löschen
                        '',                                               // Beschreibung init Text
                    ];

                    $lines[] = $this->escapeCsvRow($row);
                }
            }
        }

        // UTF-8 BOM für Excel-Kompatibilität
        $csvData = "\xEF\xBB\xBF" . implode("\n", $lines);
        
        // Exports in geschütztem Storage-Verzeichnis speichern (nicht public)
        Storage::disk('local')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * INFONIQA Zeitwirtschaft-Export (Laufender Monat: 1. des Monats bis heute)
     */
    private function exportInfoniqaZeitwirtschaftMonat(HcmExport $export): string
    {
        $filename = 'infoniqa_zeitwirtschaft_monat_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        $parameters = $export->parameters ?? [];
        $employerId = $parameters['employer_id'] ?? null;

        if (!$employerId) {
            throw new \InvalidArgumentException('INFONIQA Zeitwirtschaft Export benötigt employer_id in den Parametern');
        }

        $employer = HcmEmployer::findOrFail($employerId);
        if ($employer->team_id !== $this->teamId) {
            throw new \InvalidArgumentException('Arbeitgeber gehört nicht zum aktuellen Team');
        }

        // Zeitraum für Stundenlöhner: Gesamter Vormonat (1. bis letzter Tag des Vormonats)
        // Zeitraum für Abwesenheitstage/Urlaub: kompletter aktueller Monat (inkl. zukünftiger Tage)
        $now = Carbon::now();
        
        // Gesamter Vormonat für Stundenlöhner
        $lastMonth = $now->copy()->subMonth();
        $hoursFromDate = Carbon::create($lastMonth->year, $lastMonth->month, 1);
        $hoursToDate = Carbon::create($lastMonth->year, $lastMonth->month, $lastMonth->daysInMonth);
        $monthDisplay = $now->format('m.Y');
        
        // Zeitraum für Abwesenheitstage/Urlaub (kompletter Monat)
        $absenceFromDate = $now->copy()->startOfMonth();
        $absenceToDate = $now->copy()->endOfMonth();

        // Headlines definieren
        $headers = [
            'Monat',
            'Von Datum (F:',
            'Bis Datum (FZ)',
            'Vorname',
            'Name',
            'MA Code ZW',
            'LA Code ZW',
            'Einheiten (Std.)',
            'Tage',
            'Satz',
            'Betrag',
            'Prozent',
            'Fehlzeitencode',
            'Kostenstelle',
            'Kostenträger',
            'Tarifart',
            'Tarifgruppe',
            'Tarifstufe',
            'zu löschen',
            'Beschreibung init Text',
        ];

        $lines = [];
        $lines[] = $this->escapeCsvRow($headers);

        // Lade alle Mitarbeiter mit aktiven Verträgen (Stundenlohn)
        $employees = HcmEmployee::with([
                'crmContactLinks.contact',
                'contracts' => function ($query) {
                    $query->where('is_active', true)
                          ->where('wage_base_type', 'Stundenlohn');
                },
                'contracts.costCenterLinks.costCenter',
                'contracts.tariffGroup.tariffAgreement',
                'contracts.tariffLevel',
            ])
            ->where('team_id', $this->teamId)
            ->where('employer_id', $employerId)
            ->where('is_active', true)
            ->get();

        // Für jeden Vertrag mit Stundenlohn eine Zeile erstellen
        foreach ($employees as $employee) {
            $contact = $employee->crmContactLinks->first()?->contact;
            $firstName = $contact?->first_name ?? '';
            $lastName = $contact?->last_name ?? '';
            $employeeNumber = (string)($employee->employee_number ?? '');

            foreach ($employee->contracts as $contract) {
                // Kostenstelle vom Vertrag holen
                $costCenter = $contract->getCostCenter();
                $costCenterCode = $costCenter?->code ?? $contract->cost_center ?? '';

                // Tarif-Informationen
                $tariffType = $contract->tariffGroup?->tariffAgreement?->name ?? '';
                $tariffGroup = $contract->tariffGroup?->code ?? '';
                $tariffLevel = $this->formatTariffLevel($contract->tariffLevel);

                // Zeiterfassungsdaten für den Abrechnungszeitraum abfragen (15.12.2025 bis 14.01.2026)
                $timeRecords = HcmContractTimeRecord::where('contract_id', $contract->id)
                    ->whereBetween('record_date', [$hoursFromDate->toDateString(), $hoursToDate->toDateString()])
                    ->whereNotNull('work_minutes')
                    ->get();

                // Anzahl der Tage (eindeutige Datumsangaben)
                $daysCount = $timeRecords->pluck('record_date')->unique()->count();

                // Gesamtstunden berechnen (work_minutes in Stunden umrechnen)
                $totalMinutes = $timeRecords->sum('work_minutes');
                $totalHours = $totalMinutes > 0 ? round($totalMinutes / 60, 2) : 0;
                
                // Nur Zeile erstellen, wenn Stunden angefallen sind
                if ($totalHours > 0) {
                    // Stundenformatierung mit Komma statt Punkt
                    $totalHoursFormatted = str_replace('.', ',', number_format($totalHours, 2, '.', ''));

                    // Zeile erstellen
                    $row = [
                        $monthDisplay,                                    // Monat
                        $hoursFromDate->format('d.m.Y'),                  // Von Datum (F: (1. des Vormonats)
                        $hoursToDate->format('d.m.Y'),                     // Bis Datum (FZ) (letzter Tag des Vormonats)
                        $firstName,                                       // Vorname
                        $lastName,                                        // Name
                        $employeeNumber,                                  // MA Code ZW
                        '',                                               // LA Code ZW
                        $totalHoursFormatted,                             // Einheiten (Std.) - mit Komma
                        $daysCount > 0 ? (string)$daysCount : '',        // Tage
                        '',                                               // Satz
                        '',                                               // Betrag
                        '',                                               // Prozent
                        '',                                               // Fehlzeitencode
                        $costCenterCode,                                  // Kostenstelle
                        '',                                               // Kostenträger
                        $tariffType,                                      // Tarifart
                        $tariffGroup,                                     // Tarifgruppe
                        $tariffLevel,                                     // Tarifstufe
                        '',                                               // zu löschen
                        '',                                               // Beschreibung init Text
                    ];
                    
                    $lines[] = $this->escapeCsvRow($row);
                }
            }
        }

        // Abwesenheitstage aus dem laufenden Monat hinzufügen (1. des Monats bis heute)
        $absenceDays = HcmContractAbsenceDay::with(['contract.employee.crmContactLinks.contact', 'contract', 'absenceReason'])
            ->where('team_id', $this->teamId)
            ->whereHas('contract.employee', function ($query) use ($employerId) {
                // WICHTIG: Auch inaktive Mitarbeiter berücksichtigen (Payroll/Export-Logik)
                $query->where('employer_id', $employerId);
            })
            ->whereBetween('absence_date', [$absenceFromDate->toDateString(), $absenceToDate->toDateString()])
            ->orderBy('absence_date')
            ->get();

        // Gruppiere zuerst nach Person (Employee/Vertrag), dann nach Grund
        $absenceByEmployee = $absenceDays->groupBy('employee_id');
        
        foreach ($absenceByEmployee as $employeeId => $employeeAbsences) {
            // Für jede Person: Gruppiere nach Vertrag und Grund
            $absenceByContractAndReason = $employeeAbsences->groupBy(function ($absence) {
                return $absence->contract_id . '_' . ($absence->absence_reason_id ?? 'null');
            });
            
            foreach ($absenceByContractAndReason as $group) {
                $firstAbsence = $group->first();
                $contract = $firstAbsence->contract;
                if (!$contract) {
                    continue;
                }
                $employee = $contract->employee;
                if (!$employee) {
                    continue;
                }
                $contact = $employee->crmContactLinks->first()?->contact;
                
                $firstName = $contact?->first_name ?? '';
                $lastName = $contact?->last_name ?? '';
                $employeeNumber = (string)($employee->employee_number ?? '');
                
                // Kostenstelle vom Vertrag holen
                $costCenter = $contract->getCostCenter();
                $costCenterCode = $costCenter?->code ?? $contract->cost_center ?? '';
                
                // Sortiere nach Datum
                $sortedAbsences = $group->sortBy('absence_date')->values();
                
                // Konsolidiere: Wenn mehrere Tage am Stück mit gleichem Grund, dann zusammenfassen
                $consolidated = [];
                $currentStart = null;
                $currentEnd = null;
                $currentStartAbsence = null;
                $currentHoursSum = 0.0;
                $currentHoursHasValue = false;
                
                foreach ($sortedAbsences as $absence) {
                    $absenceDate = Carbon::parse($absence->absence_date);
                    $dayHours = $absence->absence_hours;
                    if ($dayHours !== null && $dayHours !== '') {
                        $currentHoursSum += (float) $dayHours;
                        $currentHoursHasValue = true;
                    }
                    
                    if ($currentStart === null) {
                        // Erster Tag einer Sequenz
                        $currentStart = $absenceDate;
                        $currentEnd = $absenceDate;
                        $currentStartAbsence = $absence;
                    } elseif ($absenceDate->diffInDays($currentEnd) === 1) {
                        // Fortsetzung der Sequenz (nächster Tag) - gleicher Grund bereits durch Gruppierung sichergestellt
                        $currentEnd = $absenceDate;
                    } else {
                        // Unterbrechung: Speichere aktuelle Sequenz und starte neue
                        $consolidated[] = [
                            'start' => $currentStart,
                            'end' => $currentEnd,
                            'days' => $currentStart->diffInDays($currentEnd) + 1,
                            'hours' => $currentHoursHasValue ? round($currentHoursSum, 2) : null,
                            'reason' => $currentStartAbsence->absenceReason?->code ?? '',
                        ];
                        $currentStart = $absenceDate;
                        $currentEnd = $absenceDate;
                        $currentStartAbsence = $absence;
                        $currentHoursSum = 0.0;
                        $currentHoursHasValue = false;
                        $dayHours = $absence->absence_hours;
                        if ($dayHours !== null && $dayHours !== '') {
                            $currentHoursSum += (float) $dayHours;
                            $currentHoursHasValue = true;
                        }
                    }
                }
                
                // Letzte Sequenz hinzufügen
                if ($currentStart !== null) {
                    $consolidated[] = [
                        'start' => $currentStart,
                        'end' => $currentEnd,
                        'days' => $currentStart->diffInDays($currentEnd) + 1,
                        'hours' => $currentHoursHasValue ? round($currentHoursSum, 2) : null,
                        'reason' => $currentStartAbsence->absenceReason?->code ?? '',
                    ];
                }
                
                // Erstelle Zeilen für konsolidierte Abwesenheiten
                foreach ($consolidated as $consolidation) {
                    $absenceHoursFormatted = '';
                    if (!empty($consolidation['hours']) && (float) $consolidation['hours'] > 0) {
                        $absenceHoursFormatted = str_replace('.', ',', number_format((float) $consolidation['hours'], 2, '.', ''));
                    }
                    $row = [
                        $monthDisplay,                                    // Monat
                        $consolidation['start']->format('d.m.Y'),        // Von Datum (F:
                        $consolidation['end']->format('d.m.Y'),          // Bis Datum (FZ)
                        $firstName,                                       // Vorname
                        $lastName,                                        // Name
                        $employeeNumber,                                  // MA Code ZW
                        '',                                               // LA Code ZW
                        $absenceHoursFormatted,                            // Einheiten (Std.)
                        (string)$consolidation['days'],                   // Tage
                        '',                                               // Satz
                        '',                                               // Betrag
                        '',                                               // Prozent
                        $consolidation['reason'],                         // Fehlzeitencode (Grund der ersten Abwesenheit)
                        $costCenterCode,                                  // Kostenstelle
                        '',                                               // Kostenträger
                        '',                                               // Tarifart (leer für Abwesenheiten)
                        '',                                               // Tarifgruppe (leer für Abwesenheiten)
                        '',                                               // Tarifstufe (leer für Abwesenheiten)
                        '',                                               // zu löschen
                        '',                                               // Beschreibung init Text
                    ];
                    
                    $lines[] = $this->escapeCsvRow($row);
                }
            }
        }

        // Urlaubstage aus dem laufenden Monat hinzufügen (1. des Monats bis heute)
        $vacationDays = HcmContractVacationDay::with([
                'contract.employee.crmContactLinks.contact',
                'contract.costCenterLinks.costCenter',
                'contract.tariffGroup.tariffAgreement',
                'contract.tariffLevel',
            ])
            ->where('team_id', $this->teamId)
            ->whereHas('contract.employee', function ($query) use ($employerId) {
                // WICHTIG: Auch inaktive Mitarbeiter berücksichtigen (Payroll/Export-Logik)
                $query->where('employer_id', $employerId);
            })
            ->whereBetween('vacation_date', [$absenceFromDate->toDateString(), $absenceToDate->toDateString()])
            ->orderBy('vacation_date')
            ->get();

        // Gruppiere zuerst nach Person (Employee/Vertrag)
        $vacationByEmployee = $vacationDays->groupBy('employee_id');
        
        foreach ($vacationByEmployee as $employeeId => $employeeVacations) {
            // Für jede Person: Gruppiere nach Vertrag
            $vacationByContract = $employeeVacations->groupBy('contract_id');
            
            foreach ($vacationByContract as $group) {
                $firstVacation = $group->first();
                $contract = $firstVacation->contract;
                if (!$contract) {
                    continue;
                }
                $employee = $contract->employee;
                if (!$employee) {
                    continue;
                }
                $contact = $employee->crmContactLinks->first()?->contact;

                $firstName = $contact?->first_name ?? '';
                $lastName = $contact?->last_name ?? '';
                $employeeNumber = (string)($employee->employee_number ?? '');

                // Kostenstelle vom Vertrag holen
                $costCenter = $contract->getCostCenter();
                $costCenterCode = $costCenter?->code ?? $contract->cost_center ?? '';

                // WICHTIG: Urlaub nicht konsolidieren / nicht summieren -> 1 Zeile pro Tag
                foreach ($group->sortBy('vacation_date')->values() as $vacation) {
                    $vacationDate = Carbon::parse($vacation->vacation_date);

                    $vacationHoursFormatted = '';
                    if ($vacation->vacation_hours !== null && $vacation->vacation_hours !== '' && (float) $vacation->vacation_hours > 0) {
                        $vacationHoursFormatted = str_replace('.', ',', number_format((float) $vacation->vacation_hours, 2, '.', ''));
                    }

                    $row = [
                        $monthDisplay,                                    // Monat
                        $vacationDate->format('d.m.Y'),                   // Von Datum (F:
                        $vacationDate->format('d.m.Y'),                   // Bis Datum (FZ)
                        $firstName,                                       // Vorname
                        $lastName,                                        // Name
                        $employeeNumber,                                  // MA Code ZW
                        '',                                               // LA Code ZW
                        $vacationHoursFormatted,                          // Einheiten (Std.)
                        '1',                                              // Tage (pro Datensatz)
                        '',                                               // Satz
                        '',                                               // Betrag
                        '',                                               // Prozent
                        'URLAUB',                                         // Fehlzeitencode
                        $costCenterCode,                                  // Kostenstelle
                        '',                                               // Kostenträger
                        '',                                               // Tarifart (leer für Urlaub)
                        '',                                               // Tarifgruppe (leer für Urlaub)
                        '',                                               // Tarifstufe (leer für Urlaub)
                        '',                                               // zu löschen
                        '',                                               // Beschreibung init Text
                    ];

                    $lines[] = $this->escapeCsvRow($row);
                }
            }
        }

        // UTF-8 BOM für Excel-Kompatibilität
        $csvData = "\xEF\xBB\xBF" . implode("\n", $lines);
        
        // Exports in geschütztem Storage-Verzeichnis speichern (nicht public)
        Storage::disk('local')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * INFONIQA Bankverbindungen-Export
     */
    private function exportInfoniqaBank(HcmExport $export): string
    {
        $filename = 'infoniqa_bank_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        $parameters = $export->parameters ?? [];
        $employerId = $parameters['employer_id'] ?? null;

        if (!$employerId) {
            throw new \InvalidArgumentException('INFONIQA Bank-Export benötigt employer_id in den Parametern');
        }

        $employer = HcmEmployer::findOrFail($employerId);
        if ($employer->team_id !== $this->teamId) {
            throw new \InvalidArgumentException('Arbeitgeber gehört nicht zum aktuellen Team');
        }

        $employees = HcmEmployee::with([
                'contracts',
                'benefits' => function ($query) {
                    $query->where('is_active', true);
                },
                'crmContactLinks.contact',
            ])
            ->where('team_id', $this->teamId)
            ->where('employer_id', $employerId)
            ->where('is_active', true)
            ->orderBy('employee_number')
            ->get();

        $lines = [];
        $row1 = $this->getInfoniqaBankRow1();
        $row1[0] = (string)($employer->employer_number ?? '');
        $lines[] = $this->escapeCsvRow($row1);
        $lines[] = $this->escapeCsvRow($this->getInfoniqaBankRow2());
        $lines[] = $this->escapeCsvRow($this->getInfoniqaBankRow3());
        $lines[] = $this->escapeCsvRow($this->getInfoniqaBankRow4());
        $lines[] = $this->escapeCsvRow($this->getInfoniqaBankHeaders());

        foreach ($employees as $employee) {
            if (empty($employee->employee_number)) {
                continue;
            }

            $rows = $this->buildInfoniqaBankRowsForEmployee($employee, $employer);
            if (empty($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $lines[] = $this->escapeCsvRow($row);
            }
        }

        $csvData = "\xEF\xBB\xBF" . implode("\n", $lines);
        Storage::disk('local')->put($filepath, $csvData);

        return $filepath;
    }

    private function getInfoniqaBankRow1(): array
    {
        return ['5484811', 'Konv Sonstige Bankverbindung', '', '', '', '', '', '', '', ''];
    }

    private function getInfoniqaBankRow2(): array
    {
        return ['Bankverbindung', 'Bankverbindung', '', 'Bankverbindung', 'Bankverbindung', 'Bankverbindung', 'Bankverbindung', '', 'spezielle Felder', ''];
    }

    private function getInfoniqaBankRow3(): array
    {
        return ['3', '4', '5', '10', '14', '46', '45', '15', '5485000', ''];
    }

    private function getInfoniqaBankRow4(): array
    {
        return ['Code20', 'Code20', 'Date', 'Option', 'Text70', 'Code44', 'Code11', 'Text140', 'Text30', ''];
    }

    private function getInfoniqaBankHeaders(): array
    {
        return ['Mitarbeiternr', 'Bankvorgangscode', 'gültig ab', 'Zahlungsweg', 'Empfänger', 'IBAN', 'BIC/Swift-Code', 'Verwendungszweck', 'Von Mandant', 'Hinweise'];
    }

    private function buildInfoniqaBankRowsForEmployee(HcmEmployee $employee, HcmEmployer $employer): array
    {
        $rows = [];
        $mandant = $this->formatBankMandant($employer);
        $employeeNumber = (string) $employee->employee_number;

        $contracts = $employee->relationLoaded('contracts') ? $employee->contracts : $employee->contracts()->get();
        $contract = $employee->activeContract();
        if (!$contract && $contracts->count()) {
            $contract = $contracts->sortByDesc('start_date')->first();
        }

        $contractStart = $contract?->start_date ? Carbon::parse($contract->start_date) : null;

        $recipient = $this->determineEmployeeRecipient($employee);
        $iban = $this->formatIban($employee->bank_iban);
        $bic = $employee->bank_swift ? strtoupper(trim($employee->bank_swift)) : '';

        $salaryHints = [];
        if ($iban === '') {
            $salaryHints[] = 'IBAN fehlt';
        }

        if ($recipient !== '' || $iban !== '' || $bic !== '') {
            $rows[] = $this->makeInfoniqaBankRow(
                $employeeNumber,
                'AUSZAHLUNG',
                $this->formatBankDate($contractStart),
                'Überweisung',
                $recipient,
                $iban,
                $bic,
                '',
                $mandant,
                $this->composeHint($salaryHints)
            );
        }

        $benefits = $employee->relationLoaded('benefits')
            ? $employee->benefits
            : $employee->benefits()->where('is_active', true)->get();

        $benefitsByType = $benefits->groupBy('benefit_type');

        $vwlIndex = 1;
        foreach ($benefitsByType->get('vwl', collect()) as $benefit) {
            $data = $benefit->benefit_specific_data ?? [];
            $benefitIban = $this->formatIban($data['account_number'] ?? $data['iban'] ?? null);
            $benefitDate = $benefit->start_date ? Carbon::parse($benefit->start_date) : $contractStart;
            $amount = $this->parseMonetary($benefit->monthly_contribution_employee);

            $purposeParts = [];
            if (!empty($benefit->contract_number)) {
                $purposeParts[] = 'Vertrag ' . $benefit->contract_number;
            }
            if ($amount !== null) {
                $purposeParts[] = 'Rate ' . $this->formatEuro($amount);
            }

            $vwlHints = [];
            if ($benefitIban === '') {
                $vwlHints[] = 'IBAN fehlt';
            }

            $rows[] = $this->makeInfoniqaBankRow(
                $employeeNumber,
                'VWL' . $vwlIndex,
                $this->formatBankDate($benefitDate),
                'Überweisung',
                $this->truncate($benefit->insurance_company ?: ($benefit->name ?: 'VWL'), 70),
                $benefitIban,
                '',
                $this->composePurpose($purposeParts),
                $mandant,
                $this->composeHint($vwlHints)
            );

            $vwlIndex++;
        }

        $jobradIndex = 1;
        foreach ($benefitsByType->get('jobrad', collect()) as $benefit) {
            $data = $benefit->benefit_specific_data ?? [];
            $benefitDate = $benefit->start_date ? Carbon::parse($benefit->start_date) : $contractStart;
            $amount = $this->parseMonetary($benefit->monthly_contribution_employee);

            $purposeParts = [];
            if (!empty($benefit->contract_number)) {
                $purposeParts[] = 'Vertrag ' . $benefit->contract_number;
            }
            if ($amount !== null) {
                $purposeParts[] = 'Rate ' . $this->formatEuro($amount);
            }

            $hintParts = [];
            if (!empty($data['cost_center'])) {
                $hintParts[] = 'KST ' . $data['cost_center'];
            }

            $rows[] = $this->makeInfoniqaBankRow(
                $employeeNumber,
                'JOBRAD' . ($jobradIndex > 1 ? $jobradIndex : ''),
                $this->formatBankDate($benefitDate),
                'Einzug',
                $this->truncate($benefit->name ?: 'JobRad Leasing', 70),
                '',
                '',
                $this->composePurpose($purposeParts),
                $mandant,
                $this->composeHint($hintParts)
            );

            $jobradIndex++;
        }

        $bkvIndex = 1;
        foreach ($benefitsByType->get('bkv', collect()) as $benefit) {
            $benefitDate = $benefit->start_date ? Carbon::parse($benefit->start_date) : $contractStart;
            $amount = $this->parseMonetary($benefit->monthly_contribution_employee);

            $purposeParts = [];
            if (!empty($benefit->contract_number)) {
                $purposeParts[] = 'Vertrag ' . $benefit->contract_number;
            }
            if (!empty($benefit->description) && $benefit->description !== '[leer]') {
                $purposeParts[] = $benefit->description;
            }
            if ($amount !== null) {
                $purposeParts[] = 'Rate ' . $this->formatEuro($amount);
            }

            $rows[] = $this->makeInfoniqaBankRow(
                $employeeNumber,
                'SONSTIGE ' . $bkvIndex,
                $this->formatBankDate($benefitDate),
                'Einzug',
                $this->truncate($benefit->insurance_company ?: 'Allianz', 70),
                '',
                '',
                $this->composePurpose($purposeParts),
                $mandant,
                ''
            );

            $bkvIndex++;
        }

        $bavIndex = 1;
        foreach ($benefitsByType->get('bav', collect()) as $benefit) {
            $benefitDate = $benefit->start_date ? Carbon::parse($benefit->start_date) : $contractStart;

            $purposeParts = [];
            if (!empty($benefit->contract_number)) {
                $purposeParts[] = 'Vertrag ' . $benefit->contract_number;
            }
            if (!empty($benefit->description) && $benefit->description !== '[leer]') {
                $purposeParts[] = $benefit->description;
            }

            $rows[] = $this->makeInfoniqaBankRow(
                $employeeNumber,
                'BAV' . ($bavIndex > 1 ? $bavIndex : ''),
                $this->formatBankDate($benefitDate),
                'Überweisung',
                $this->truncate($benefit->insurance_company ?: 'Betriebliche Altersvorsorge', 70),
                '',
                '',
                $this->composePurpose($purposeParts),
                $mandant,
                $this->composeHint(['IBAN fehlt'])
            );

            $bavIndex++;
        }

        return $rows;
    }

    private function makeInfoniqaBankRow(
        string $employeeNumber,
        string $bankCode,
        string $validFrom,
        string $paymentMethod,
        string $recipient,
        string $iban,
        string $bic,
        string $purpose,
        string $mandant,
        string $hint
    ): array {
        return [
            $employeeNumber,
            $bankCode,
            $validFrom,
            $paymentMethod,
            $this->truncate($recipient, 70),
            $this->truncate($iban, 35),
            $this->truncate($bic, 11),
            $this->truncate($purpose, 140),
            $this->truncate($mandant, 30),
            $this->truncate($hint, 30),
        ];
    }

    /**
     * Generiert INFONIQA-CSV Format (mehrere Header-Zeilen)
     */
    private function generateInfoniqaCsv(Collection $employees, HcmEmployer $employer): string
    {
        $lines = [];
        $headers = $this->getInfoniqaHeaders();
        $totalColumns = count($headers);
        
        // Zeile 1 aus Vorlage übernehmen und Mandantennummer setzen
        $row1 = $this->getInfoniqaRow1();
        $row1[0] = (string)($employer->employer_number ?? '');
        $lines[] = $this->escapeCsvRow($row1);
        
        // Zeile 2: Kategorien (exakt nach Vorlage)
        $row2 = $this->getInfoniqaRow2();
        $lines[] = $this->escapeCsvRow($row2);
        
        // Zeile 3: Feldtypen (exakt nach Vorlage)
        $row3 = $this->getInfoniqaRow3();
        $lines[] = $this->escapeCsvRow($row3);
        
        // Zeile 4: Datentypen (exakt nach Vorlage)
        $row4 = $this->getInfoniqaRow4();
        $lines[] = $this->escapeCsvRow($row4);
        
        // Zeile 5: Spaltenüberschriften (exakt nach Vorlage)
        $row5 = $this->getInfoniqaHeaders();
        $lines[] = $this->escapeCsvRow($row5);
        
        // Datenzeilen
        foreach ($employees as $employee) {
            foreach ($employee->contracts as $contract) {
                $lines[] = $this->generateInfoniqaEmployeeRow($employee, $contract);
            }
        }
        
        // Trailer-Zeile: Leere Spalten + "0000"
        $trailer = array_fill(0, $totalColumns, '');
        $trailer[12] = '0000'; // PLZ Code (Spalte 13, Index 12)
        $lines[] = $this->escapeCsvRow($trailer);
        
        return implode("\n", $lines);
    }

    /**
     * Zeile 1: Kopfzeile (Mandanten-/Konv-Info)
     */
    private function getInfoniqaRow1(): array
    {
        // Originalzeile aus Vorlage; Mandantennummer wird später überschrieben
        $values = explode(';', '5484810;Konv Mitarbeiter;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;Fehlt im neuen KonvTool;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;WfbM;;;;;;;;;;;;;;;;;ATZ;;;;KUG;;;;ZVK;;;;Bau;;;BV;;;;;;');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 2: Kategorien
     */
    private function getInfoniqaRow2(): array
    {
        // Exakt aus der CSV-Vorlage kopiert
        $values = explode(';', 'Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Anfangswerte;;Mitarbeiter SV1 - WfbM;Mitarbeiter SV1 - WfbM;Mitarbeiter SV1 - WfbM;;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;;Mitarbeiter Steuer - KuG;Mitarbeiter Steuer - KuG;Mitarbeiter Steuer - KuG;;Mitarbeiter Tarif/ZVK - Zusatzversorgung;Mitarbeiter Tarif/ZVK - Zusatzversorgung;Mitarbeiter Tarif/ZVK - Zusatzversorgung;;Mitarbeiter Tarif/ZVK - Baulohn;Mitarbeiter Tarif/ZVK - Baulohn;Mitarbeiter Tarif/ZVK - Baulohn;;Berufsständisch Versicherte AN;Berufsständisch Versicherte AN;;Steuer - Aufwandspauschale;Verwaltung;spezielle Felder;spezielle Felder;spezielle Felder');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 3: Feldtypen
     */
    private function getInfoniqaRow3(): array
    {
        $values = explode(';', '1;8;5;7;2;6;3;21;25;26;27;29;22;23;313;310;300;301;302;304;321;332;306;312;305;307;333;102;101;125;105;134;110;120;126;106;127;107;128;108;117;192;123;142;143;144;104;;136;137;138;140;146;440;145;100;9;10;11;12;13;14;132;112;118;218;244;246;203;243;240;241;200;208;201;204;205;206;207;223;224;245;121;202;209;221;222;320;323;324;322;410;191;190;401;311;315;314;820;500;501;507;502;503;504;513;514;512;420;421;426;427;422;423;424;425;18;650;651;657;658;659;655;656;660;661;662;350;351;317;392;393;806;807;808;809;810;349;318;41;42;46;43;49;44;47;48;211;;178;180;181;182;185;189;;;171;177;170;;150;153;154;155;151;156;157;158;152;159;;167;168;169;165;166;;220;228;229;;590;591;592;;601;602;604;;198;197;;214;49978;5485000;5005;5504;49996');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 4: Datentypen
     */
    private function getInfoniqaRow4(): array
    {
        $values = explode(';', 'Code10;Text15;Text20;Text15;Text30;Text15;Text30;Text30;Code10;Text10;Text30;Code3;Code10;Text30;Code10;Code10;Date;Date;Code20;Date;Code20;Date;Date;Date;Date;Date;Date;Code3;Code4;Option;Option;Option;Code20;Decimal;Option;Option;Option;Option;Option;Option;Option;Option;Decimal;Boolean;Boolean;Boolean;Code3;;Date;Date;Option;Boolean;Code10;Date;Boolean;Code12;Option;Date;Text30;Text30;Text15;Text15;Code3;Code20;Option;Option;Option;Option;Code11;Code10;Boolean;Option;Option;Decimal;Decimal;Decimal;Decimal;Decimal;Decimal;Code20;Code20;Option;Decimal;Integer;Boolean;Option;Option;Code10;Decimal;Decimal;Code20;Code20;Option;Code9;Code10;Code20;Code20;Option;Option;Code20;Code20;Date;Code20;Date;Code20;Decimal;Option;Option;Option;Option;Code20;Code20;Text50;Text30;Date;Date;Option;Date;Date;Date;Date;Option;Date;Date;Date;Date;Option;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code10;Text30;Text30;Text30;Text80;Text30;Text30;Text30;Text80;Text80;Test20;;Option;Option;Option;Decimal;Boolean;Option;Code20;;Code20;Code20;Boolean;;Date;Date;Date;Date;Decimal;Decimal;Boolean;Boolean;Decimal;Decimal;;Date;Date;Code20;Text30;Text30;;Option;Date;Option;;Code20;Date;Code20;;Option;Option;Boolean;;Code17;Boolean;;Option;Text200;Text30;Code10;Integer;Decimal');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 5: Header
     */
    private function getInfoniqaHeaders(): array
    {
        $values = explode(';', 'Nr.;Anrede;Titel;Namenszusatz;Vorname;Namensvorsatz;Name;Straße;Hausnr.;Hausnr.-zusatz;Adresszusatz;Länderkennzeichen;PLZ Code;Ort;Betriebsteilcode;Abrechnungskreis;Eintrittsdatum;Austrittsdatum;Austrittsgrund;Vertragsende;Befristungsgrund;Ende Probezeit;Entlassung / Kündigung am;unwiderrufliche Freistellung am;Betriebszugehörigkeit seit;Berufserf./Ausbildung seit;Ende der Ausbildung;Personengruppenschlüssel;Beitragsgruppe;BYGR KV;KV-Kennzeichen;KV Vertragsart;KK-Code (Einzugsstelle);KV-Beitrag privat;BYGR RV;RV-Kennzeichen;BYGR AV;AV-Kennzeichen;BYGR PV;PV-Kennzeichen;Kinder;Kinder unter 25 Jahren für PV-Abschlag;PV-Beitrag privat;Umlagepflicht U1 Lfz;Umlagepflicht U2;Umlagepflicht Insolvenz;Staatsangehörigkeitsschlüssel;;Rentenbeginn;Befreiung von RV-Pflicht ab (§ 6 Abs.1b SGBVI);Beschäftigungsverhältnis;Mehrfachbeschäftigt;Rentenart;Altersrente beantragt am;Saisonarbeitnehmer;Soz.-Versicherungsnr.;Geschlecht;Geburtsdatum;Geburtsname;Geburtsort;Namenszusatz Geburtsname;Namensvorsatz Geburtsname;Geburtsland;Krankenkasse (tats.);PGR 109/110:Versicherungsstatus;steuerpflichtig EStG § 1;Art der Besteuerung;wer trägt die Steuer;Identifikationsnummer;abw. Geburtsdatum lt. Pass;Haupt AG (Steuer);Herkunft LSt.-Merkmale gem. EStG;Steuerklasse;Faktor nach § 39f EStG;Kinderfreibetrag;Freibetrag Monat;Freibetrag Jahr;Hinzurechnung Monat;Hinzurechnung Jahr;Konfession;Konfession Ehepartner;Lohnsteuerspezifikation;KV/PV Basistarif privat;Kilometer (FWA);kein LSt.-Jahresausgleich;Arbeitskammer Pflicht;Sammelbeförderung;Arbeitszeitvereinbarung;Teilzeitfaktor;Entgeltfaktor;Teilzeit Grund;Funktion;Beschäftigung in;Tätigkeitsschlüssel;UV Zuordnung;Berechnungsmerkmal;Statistiktyp;Entgelt Art;§5 EntgFG: ärztliche AU-Feststellung spätestens am;Tarifart;Tarifgruppe;Tarifbasisdatum;Tarifstufe;Tarifstufe seit;Tarifgebiet;Tarifprozent;Ausschluss tarifl. Sonderzahlung;Urlaubsverwaltung;Arbeitsplatz lt. § 156 SGB IX;Arbeitszeitschlüssel für REHADAT;Schwerbehindert Pers.gruppe;Dienststelle;Ort Dienststelle;Aktenzeichen des Ausweises;Ausweis ab;Ausweis bis;Familienstand;Mutmaßlicher Entbindungstag;tats. Entbindungstag;Beschäftigungsverbot Beginn;Beschäftigungsverbot Ende;Beschäftigungsverbot Art;Schutzfrist Beginn;Schutzfrist Ende;Elternzeit Beginn;Elternzeit Ende;Elternzeit Art;Ordnungsmerkmal Wert 01;Ordnungsmerkmal Wert 02;Ordnungsmerkmal Wert 03;Ordnungsmerkmal Wert 04;Ordnungsmerkmal Wert 05;Ordnungsmerkmal Wert 06;Ordnungsmerkmal Wert 07;Ordnungsmerkmal Wert 08;Ordnungsmerkmal Wert 09;Ordnungsmerkmal Wert 10;Buchungsgruppencode;freier Text;Telefonnr. (privat);Mobiltelefonnr. (privat);E-Mail (privat);Telefonnr. (dienstl.);Mobiltelefonnr. (dienstl.);Faxnr. (dienstl.);E-Mail (dienstl.);E-Mail (E-Post;nationale ID;;ist Vers.-Bezug gem. §229;Beitragsabführungspflicht;Mehrfachbezug;max. beitragspfl. Vers.-Bezug;Beihilfe berechtigt;Zahlungszyklus;Aktenzeichen;;§ 168 SGB VI Leistungsträger WfbM;§ 179 SGB VI Leistungsträger WfbM;Heimkostenbeteiligung WfbM;;ATZ Vertrag vom;ATZ Beginn;ATZ Blockmodell Beginn;ATZ Freizeitphase Beginn;ATZ RV Prozent;ATZ Begrenzung ZBE;ATZ UB/ZBE bei Kr.-Geld;ATZ Aufstockung bei Kr.-Geld;ATZ Netto Prozent;ATZ Brutto Prozent;;Flex Beginn;Flex Ende;Flex Institut;Flex Vertragsnr WGH;Flex Vertragsnr. (WGH-AG);;KuG Leistungssatz;KuG Beginn;KuG Leistungsgruppe f. Grenzgänger;;Kasse (ZV);Vertragsbeginn ZV;Mitgliedsnr. in ZV;;Arbeitnehmergruppe;Winterb.-Umlage;Siko-Flex Meldung;;BV-Mitgliedsnummer;BV Selbszahler;;Aufwandspauschale;Funktionsbeschreibung;Von Mandant;KK Betriebsnummer;Tätigkeitscode Lfdnr.;Abschlagsbetrag');
        return array_pad($values, 199, '');
    }

    /**
     * Liefert Index-Zuordnungen für benötigte Spaltennamen
     */
    private function getInfoniqaColumnIndexes(): array
    {
        static $indexes = null;

        if ($indexes !== null) {
            return $indexes;
        }

        $headers = $this->getInfoniqaHeaders();

        $labels = [
            'employee_number' => 'Nr.',
            'salutation' => 'Anrede',
            'first_name' => 'Vorname',
            'last_name' => 'Name',
            'street' => 'Straße',
            'house_number' => 'Hausnr.',
            'country_code' => 'Länderkennzeichen',
            'postal_code' => 'PLZ Code',
            'city' => 'Ort',
            'payroll_circle' => 'Abrechnungskreis',
            'entry_date' => 'Eintrittsdatum',
            'exit_date' => 'Austrittsdatum',
            'contract_end' => 'Vertragsende',
            'probation_end' => 'Ende Probezeit',
            'seniority_since' => 'Betriebszugehörigkeit seit',
            'person_group' => 'Personengruppenschlüssel',
            'contribution_group' => 'Beitragsgruppe',
            'kv_contract_type' => 'KV Vertragsart',
            'health_insurance_code' => 'KK-Code (Einzugsstelle)',
            'children' => 'Kinder',
            'children_pv' => 'Kinder unter 25 Jahren für PV-Abschlag',
            'levy_u1' => 'Umlagepflicht U1 Lfz',
            'levy_u2' => 'Umlagepflicht U2',
            'levy_insolvency' => 'Umlagepflicht Insolvenz',
            'nationality' => 'Staatsangehörigkeitsschlüssel',
            'rentenbeginn' => 'Rentenbeginn',
            'rv_exemption' => 'Befreiung von RV-Pflicht ab (§ 6 Abs.1b SGBVI)',
            'employment_relationship' => 'Beschäftigungsverhältnis',
            'multiple_employment' => 'Mehrfachbeschäftigt',
            'pension_type' => 'Rentenart',
            'seasonal_worker' => 'Saisonarbeitnehmer',
            'social_security_number' => 'Soz.-Versicherungsnr.',
            'gender' => 'Geschlecht',
            'birth_date' => 'Geburtsdatum',
            'birth_name' => 'Geburtsname',
            'birth_place' => 'Geburtsort',
            'birth_country' => 'Geburtsland',
            'health_insurance_name' => 'Krankenkasse (tats.)',
            'insurance_status' => 'PGR 109/110:Versicherungsstatus',
            'tax_residency' => 'steuerpflichtig EStG § 1',
            'tax_type' => 'Art der Besteuerung',
            'tax_payer' => 'wer trägt die Steuer',
            'tax_id' => 'Identifikationsnummer',
            'main_employer' => 'Haupt AG (Steuer)',
            'tax_feature_origin' => 'Herkunft LSt.-Merkmale gem. EStG',
            'tax_class' => 'Steuerklasse',
            'child_allowance' => 'Kinderfreibetrag',
            'allowance_month' => 'Freibetrag Monat',
            'allowance_year' => 'Freibetrag Jahr',
            'addition_month' => 'Hinzurechnung Monat',
            'addition_year' => 'Hinzurechnung Jahr',
            'church_tax' => 'Konfession',
            'kv_pv_basic' => 'KV/PV Basistarif privat',
            'kilometer' => 'Kilometer (FWA)',
            'no_tax_annual' => 'kein LSt.-Jahresausgleich',
            'labour_chamber' => 'Arbeitskammer Pflicht',
            'collective_transport' => 'Sammelbeförderung',
            'working_time_agreement' => 'Arbeitszeitvereinbarung',
            'part_time_factor' => 'Teilzeitfaktor',
            'salary_factor' => 'Entgeltfaktor',
            'part_time_reason' => 'Teilzeit Grund',
            'job_function' => 'Funktion',
            'employment_location' => 'Beschäftigung in',
            'job_key' => 'Tätigkeitsschlüssel',
            'uv_assignment' => 'UV Zuordnung',
            'calculation_feature' => 'Berechnungsmerkmal',
            'statistics_type' => 'Statistiktyp',
            'salary_type' => 'Entgelt Art',
            'medical_certificate_date' => '§5 EntgFG: ärztliche AU-Feststellung spätestens am',
            'tariff_type' => 'Tarifart',
            'tariff_group' => 'Tarifgruppe',
            'tariff_reference_date' => 'Tarifbasisdatum',
            'tariff_level' => 'Tarifstufe',
            'tariff_level_since' => 'Tarifstufe seit',
            'tariff_area' => 'Tarifgebiet',
            'tariff_percent' => 'Tarifprozent',
            'tariff_bonus_exclusion' => 'Ausschluss tarifl. Sonderzahlung',
            'vacation_management' => 'Urlaubsverwaltung',
            'workplace_sgb' => 'Arbeitsplatz lt. § 156 SGB IX',
            'rehadat_code' => 'Arbeitszeitschlüssel für REHADAT',
            'disabled_group' => 'Schwerbehindert Pers.gruppe',
            'department' => 'Dienststelle',
            'department_city' => 'Ort Dienststelle',
            'badge_number' => 'Aktenzeichen des Ausweises',
            'badge_from' => 'Ausweis ab',
            'badge_to' => 'Ausweis bis',
            'marital_status' => 'Familienstand',
            'phone_private' => 'Telefonnr. (privat)',
            'mobile_private' => 'Mobiltelefonnr. (privat)',
            'email_private' => 'E-Mail (privat)',
            'phone_business' => 'Telefonnr. (dienstl.)',
            'mobile_business' => 'Mobiltelefonnr. (dienstl.)',
            'fax_business' => 'Faxnr. (dienstl.)',
            'email_business' => 'E-Mail (dienstl.)',
            'email_epost' => 'E-Mail (E-Post',
        ];

        foreach ($labels as $key => $label) {
            $index = array_search($label, $headers, true);
            if ($index === false) {
                throw new \RuntimeException("INFONIQA-Header '{$label}' wurde nicht gefunden.");
            }
            $indexes[$key] = $index;
        }

        // Leerspalte direkt nach Staatsangehörigkeitsschlüssel
        $rentenIndex = $indexes['rentenbeginn'] ?? array_search('Rentenbeginn', $headers, true);
        if ($rentenIndex === false) {
            throw new \RuntimeException('INFONIQA-Header "Rentenbeginn" wurde nicht gefunden.');
        }
        $indexes['blank_after_nationality'] = $rentenIndex - 1;

        return $indexes;
    }

    private function resolveInfoniqaGender($employee, $contact): ?string
    {
        $candidates = [
            $contact?->gender?->code ?? null,
            $contact?->gender?->name ?? null,
            $employee->gender ?? null,
        ];

        foreach ($candidates as $value) {
            $mapped = $this->mapGenderToken($value);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }

    private function mapGenderToken($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $token = mb_strtolower(trim((string) $value));

        if ($token === '') {
            return null;
        }

        return match ($token) {
            '-1', '1', 'male', 'männlich', 'maennlich', 'm', 'herr' => 'Männlich',
            '0', '2', 'female', 'weiblich', 'weibl.', 'w', 'f', 'frau' => 'Weiblich',
            'divers', 'diverse', 'd' => 'Divers',
            'x', 'x unbestimmt', 'unbestimmt', 'not_specified', 'nicht angegeben' => 'X unbestimmt',
            default => null,
        };
    }

    /**
     * Generiert eine Mitarbeiter-Zeile im INFONIQA-Format
     */
    private function generateInfoniqaEmployeeRow($employee, $contract): string
    {
        $contact = $employee->crmContactLinks->first()?->contact;
        $address = $contact?->postalAddresses->first();
        $costCenter = $contract?->getCostCenter();

        $headers = $this->getInfoniqaHeaders();
        $indexes = $this->getInfoniqaColumnIndexes();
        $row = array_fill(0, count($headers), '');

        $set = function (string $key, $value) use (&$row, $indexes): void {
            if (!isset($indexes[$key])) {
                return;
            }
            $row[$indexes[$key]] = $value ?? '';
        };

        $phones = ($contact?->phoneNumbers ?? collect())
            ->filter(fn($phone) => $phone && ($phone->is_active ?? true))
            ->sortByDesc(fn($phone) => (($phone->is_primary ?? false) ? 2 : 0) + ($phone->created_at?->timestamp ?? 0))
            ->values();
        $usedPhoneIds = [];

        $findPhone = function (array $preferredCodes, bool $allowReuse = false) use (&$phones, &$usedPhoneIds) {
            $preferredCodes = array_map('strtoupper', $preferredCodes);
            $allowAny = in_array('*', $preferredCodes, true);

            $match = $phones->first(function ($phone) use ($preferredCodes, $allowAny, $allowReuse, $usedPhoneIds) {
                if (!$allowReuse && in_array($phone->id, $usedPhoneIds, true)) {
                    return false;
                }

                $code = strtoupper($phone->phoneType?->code ?? '');
                if ($allowAny) {
                    return true;
                }

                return in_array($code, $preferredCodes, true);
            });

            if ($match && !$allowReuse) {
                $usedPhoneIds[] = $match->id;
            }

            return $match;
        };

        $formatPhone = static function ($phone): string {
            if (!$phone) {
                return '';
            }

            return $phone->full_phone_number
                ?? $phone->display_number
                ?? $phone->national
                ?? $phone->international
                ?? $phone->raw_input
                ?? '';
        };

        $emails = ($contact?->emailAddresses ?? collect())
            ->filter(fn($email) => $email && ($email->is_active ?? true))
            ->sortByDesc(fn($email) => (($email->is_primary ?? false) ? 2 : 0) + (($email->is_verified ?? false) ? 1 : 0))
            ->values();
        $usedEmailIds = [];

        $findEmail = function (array $preferredCodes, bool $allowReuse = false) use (&$emails, &$usedEmailIds) {
            $preferredCodes = array_map('strtoupper', $preferredCodes);
            $allowAny = in_array('*', $preferredCodes, true);

            $match = $emails->first(function ($email) use ($preferredCodes, $allowAny, $allowReuse, $usedEmailIds) {
                if (!$allowReuse && in_array($email->id, $usedEmailIds, true)) {
                    return false;
                }

                $code = strtoupper($email->emailType?->code ?? '');
                if ($allowAny) {
                    return true;
                }

                return in_array($code, $preferredCodes, true);
            });

            if ($match && !$allowReuse) {
                $usedEmailIds[] = $match->id;
            }

            return $match;
        };

        $formatEmail = static fn($email): string => $email?->email_address ?? '';

        $set('employee_number', (string)($employee->employee_number ?? ''));

        $genderName = $this->resolveInfoniqaGender($employee, $contact);

        $salutation = '';
        if ($contact?->salutation?->name) {
            $salutation = $contact->salutation->name;
        } elseif (in_array($contact?->title, ['Herr', 'Frau'], true)) {
            $salutation = $contact->title;
        } elseif ($genderName === 'Männlich') {
            $salutation = 'Herr';
        } elseif ($genderName === 'Weiblich') {
            $salutation = 'Frau';
        }
        $set('salutation', $salutation);

        $set('first_name', $contact?->first_name ?? '');
        $set('last_name', $contact?->last_name ?? '');

        $set('country_code', 'DE');
        if ($address) {
            $set('street', $address->street ?? '');
            $set('house_number', $address->house_number ?? '');
            $set('country_code', $address->country ?? 'DE');
            $set('postal_code', $this->toExcelString($address->postal_code ?? ''));
            $set('city', $address->city ?? '');
        }

        $set('payroll_circle', 'Standard');
        $set('entry_date', $contract?->start_date?->format('d.m.Y') ?? '');
        $set('exit_date', $contract?->end_date?->format('d.m.Y') ?? '');
        $set('contract_end', $contract?->end_date?->format('d.m.Y') ?? '');
        $set('probation_end', $contract?->probation_end_date?->format('d.m.Y') ?? '');
        $set('seniority_since', $contract?->start_date?->format('d.m.Y') ?? '');

        $set('person_group', $contract?->personGroup?->code ?? '');
        $set('contribution_group', '1111');

        $hasBkv = $employee->benefits->contains(fn ($benefit) => $benefit->benefit_type === 'bkv' && $benefit->is_active);
        $set('kv_contract_type', $hasBkv ? '1' : '');
        $ikNumber = $employee->healthInsuranceCompany?->ik_number
            ?? $employee->health_insurance_ik
            ?? ($employee->healthInsuranceCompany?->code ?? '');
        $set('health_insurance_code', $this->toExcelString(trim((string) $ikNumber)));

        $set('children', '');
        $set('children_pv', (string)($employee->children_count ?? 0));

        $phonePrivate = $findPhone(['PRIVATE', 'HOME', 'MOBILE']);
        if (!$phonePrivate) {
            $phonePrivate = $findPhone(['BUSINESS'], true);
        }

        $mobilePrivate = $findPhone(['MOBILE', 'PRIVATE'], false);
        if (!$mobilePrivate || ($phonePrivate && $mobilePrivate && $mobilePrivate->id === $phonePrivate->id)) {
            $mobilePrivate = $findPhone(['BUSINESS', '*'], true);
        }

        $set('phone_private', $formatPhone($phonePrivate));
        $set('mobile_private', $formatPhone($mobilePrivate));
        $set('phone_business', '');
        $set('mobile_business', '');
        $set('fax_business', '');

        $emailPrivate = $findEmail(['PRIVATE'], false);
        if (!$emailPrivate) {
            $emailPrivate = $findEmail(['*'], true);
        }

        $emailEpost = $findEmail(['EPOST', 'BILLING', 'INFO', 'BUSINESS'], true);
        if (!$emailEpost) {
            $emailEpost = $emailPrivate;
        }

        $businessEmail = trim((string)($employee->business_email ?? ''));

        $set('email_private', $formatEmail($emailPrivate));
        $set('email_business', $businessEmail);
        $set('email_epost', $formatEmail($emailEpost));

        $set('levy_u1', 'Ja');
        $set('levy_u2', 'Ja');
        $set('levy_insolvency', 'Ja');

        $set('nationality', $this->toExcelString($this->mapNationalityToCode($employee->nationality ?? null)));
        if (isset($indexes['blank_after_nationality'])) {
            $row[$indexes['blank_after_nationality']] = '';
        }
        $set('rentenbeginn', '');
        $set('rv_exemption', '');

        $employmentCode = $contract?->employmentRelationship?->code ?? '';
        $set('employment_relationship', $this->mapEmploymentRelationshipToInfoniqa($employmentCode));
        $set('multiple_employment', $contract?->has_additional_employment ? 'Ja' : 'Nein');
        $set('pension_type', $contract?->pensionType?->code ?? '');
        $set('seasonal_worker', $employee->is_seasonal_worker ? 'Ja' : 'Nein');
        $set('social_security_number', $this->toExcelString($contract?->social_security_number ?? ''));
        $set('gender', $genderName ?? '');

        $birthDate = $employee->birth_date ?? $contact?->birth_date;
        if ($birthDate) {
            $set('birth_date', is_string($birthDate) ? date('d.m.Y', strtotime($birthDate)) : $birthDate->format('d.m.Y'));
        }

        $set('birth_name', $employee->birth_surname ?? $contact?->last_name ?? '');
        $set('birth_place', $employee->birth_place ?? $contact?->birth_place ?? '');
        $set('birth_country', $employee->birth_country ?? '');
        $set('health_insurance_name', $employee->healthInsuranceCompany?->name ?? '');
        $set('insurance_status', $contract?->insuranceStatus?->code ?? '');

        $set('tax_residency', 'unbeschränkt');
        $set('tax_type', 'individuell');
        $set('tax_payer', '');
        $set('tax_id', $this->toExcelString($employee->tax_id_number ?? ''));
        $set('main_employer', 'Ja');
        $set('tax_feature_origin', 'ELStAM');
        $set('tax_class', $contract?->taxClass?->code ?? '');
        $set('child_allowance', (string)($employee->child_allowance ?? 0));
        $set('allowance_month', '');
        $set('allowance_year', '');
        $set('addition_month', '');
        $set('addition_year', '');

        if ($employee->churchTaxType) {
            $set('church_tax', $employee->churchTaxType->code);
        } else {
            $set('church_tax', $this->mapChurchTaxToCode($employee->church_tax ?? ''));
        }

        $hoursPerWeek = $contract?->hours_per_month ? ($contract->hours_per_month / 4.333) : null;
        if ($hoursPerWeek && $hoursPerWeek < 40) {
            $set('part_time_factor', number_format($hoursPerWeek / 40, 2, ',', ''));
        } elseif ($hoursPerWeek) {
            $set('part_time_factor', '1,00');
        }
        $set('salary_factor', '1,00');
        if ($hoursPerWeek) {
            $set('part_time_reason', number_format($hoursPerWeek, 2, ',', ''));
        } else {
            $set('part_time_reason', '');
        }

        $jobTitle = $contract?->jobTitles->first();
        $set('job_function', $jobTitle?->name ?? '');
        $set('employment_location', 'Firma');

        $activityCode = str_pad($contract?->primaryJobActivity?->code ?? '00000', 5, '0', STR_PAD_LEFT);
        $schooling = (string)($contract?->schooling_level ?? 0);
        $vocational = (string)($contract?->vocational_training_level ?? 0);
        $tempAgency = $contract?->is_temp_agency ? '2' : '1';
        $contractForm = (string)($contract?->contract_form ?? 0);
        $set('job_key', $activityCode . $schooling . $vocational . $tempAgency . $contractForm);

        $wageType = $contract?->wage_base_type ?? '';
        if ($wageType === 'hourly' || $contract?->hourly_wage) {
            $set('salary_type', 'Stundenlohn');
        } elseif ($wageType === 'monthly' || $contract?->base_salary) {
            $set('salary_type', 'Monatslohn/Gehalt');
        }

        $tariffAgreement = $contract?->tariffGroup?->tariffAgreement;
        if ($tariffAgreement) {
            $set('tariff_type', $tariffAgreement->name ?? '');
        }
        $set('tariff_group', $contract?->tariffGroup?->code ?? '');
        $set('tariff_reference_date', $contract?->tariff_assignment_date?->format('d.m.Y') ?? '');
        $set('tariff_level', $this->formatTariffLevel($contract?->tariffLevel));
        $set('tariff_level_since', $contract?->tariff_level_start_date?->format('d.m.Y') ?? '');
        $set('tariff_area', '');
        $set('tariff_percent', '');
        $set('tariff_bonus_exclusion', '');

        if ($contract?->vacation_entitlement) {
            $set('vacation_management', 'Jahresanspruch');
        }

        $workplaceValue = 'zählt';
        if (($employee->disability_degree ?? 0) > 0) {
            $workplaceValue = 'zählt (behindert)';
        } elseif ($hoursPerWeek && $hoursPerWeek < 18) {
            $workplaceValue = 'weniger 18 WStd. Abs.3';
        }
        $set('workplace_sgb', $workplaceValue);
        $set('rehadat_code', '');
        $set('disabled_group', '');

        if ($costCenter) {
            $name = is_object($costCenter) ? ($costCenter->name ?? '') : '';
            $set('department', $name);
            $set('department_city', $name);
        }

        $set('badge_number', '');
        $set('badge_from', '');
        $set('badge_to', '');
        $set('marital_status', $contact?->marital_status ?? '');

        return $this->escapeCsvRow($row);
    }

    /**
     * INFONIQA Dimensionen-Export (Kostenstellen je Mitarbeiter)
     */
    private function exportInfoniqaDimensions(HcmExport $export): string
    {
        $filename = 'infoniqa_dimension_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        $parameters = $export->parameters ?? [];
        $employerId = $parameters['employer_id'] ?? null;

        if (!$employerId) {
            throw new \InvalidArgumentException('INFONIQA Dimensionen Export benötigt employer_id in den Parametern');
        }

        $employer = HcmEmployer::findOrFail($employerId);
        if ($employer->team_id !== $this->teamId) {
            throw new \InvalidArgumentException('Arbeitgeber gehört nicht zum aktuellen Team');
        }

        $targetDate = Carbon::create(2025, 10, 1);
        $dateValue = $targetDate->format('d.m.Y');

        $employees = \Platform\Hcm\Models\HcmEmployee::with([
                'contracts' => function ($query) {
                    $query->where('is_active', true)
                        ->orderByDesc('start_date');
                },
                'contracts.costCenterLinks.costCenter',
            ])
            ->where('team_id', $this->teamId)
            ->where('employer_id', $employerId)
            ->where('is_active', true)
            ->get();

        $lines = [];

        foreach ($employees as $employee) {
            if (empty($employee->employee_number)) {
                continue;
            }

            $contract = $employee->contracts->first();
            if (!$contract) {
                continue;
            }

            $costCenterCode = $this->resolveCostCenterCodeForDate($contract, $targetDate);
            if ($costCenterCode === null || $costCenterCode === '') {
                continue;
            }

            $lines[] = $this->escapeCsvRow([
                $this->toExcelString($employee->employee_number),
                $dateValue,
                $costCenterCode,
                '',
                '',
                '',
                '100',
                'ja',
            ]);
        }

        $csvData = "\xEF\xBB\xBF" . implode("\n", $lines);
        Storage::disk('local')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * Payroll-Export (einfacher)
     */
    private function exportPayroll(HcmExport $export): string
    {
        // Verwendet den bestehenden PayrollTypeExportService
        $service = new PayrollTypeExportService($this->teamId);
        return $service->exportToCsv();
    }

    /**
     * Mitarbeiter-Export (einfach)
     */
    private function exportEmployees(HcmExport $export): string
    {
        $filename = 'employees_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        $parameters = $export->parameters ?? [];
        $employerId = $parameters['employer_id'] ?? null;
        $selectedFields = $parameters['fields'] ?? ['employee_number', 'last_name', 'first_name'];

        // Alle verfügbaren Felder mit Header-Namen
        $allFields = [
            'employee_number' => 'Personalnummer',
            'company_employee_number' => 'Personalnummer (Firma)',
            'last_name' => 'Nachname',
            'first_name' => 'Vorname',
            'phone' => 'Rufnummer',
            'email' => 'E-Mail',
            'primary_email' => 'Primäre E-Mail-Adresse',
            'employer' => 'Arbeitgeber',
            'status' => 'Status',
        ];

        // Query mit Arbeitgeber-Filter - Telefonnummern und E-Mail-Typen eager-loaden
        $query = \Platform\Hcm\Models\HcmEmployee::with([
                'employer',
                'contracts',
                'crmContactLinks.contact.emailAddresses.emailType',
                'crmContactLinks.contact.phoneNumbers.phoneType',
            ])
            ->where('team_id', $this->teamId);

        if ($employerId) {
            $query->where('employer_id', $employerId);
        }

        $employees = $query->get();

        // Header basierend auf ausgewählten Feldern
        $headers = [];
        foreach ($selectedFields as $field) {
            if (isset($allFields[$field])) {
                $headers[] = $allFields[$field];
            }
        }

        $csvData = [];
        $csvData[] = $this->escapeCsvRow($headers);

        // Prüfen ob Kontaktfelder benötigt werden
        $needsPhone = in_array('phone', $selectedFields, true);
        $needsEmail = in_array('email', $selectedFields, true);

        foreach ($employees as $employee) {
            $contactLink = $employee->crmContactLinks->first();
            $contact = $contactLink?->contact;

            // Rufnummer ermitteln: geschäftlich bevorzugt, Fallback privat/mobil
            $phoneValue = '';
            if ($needsPhone && $contact) {
                $phones = ($contact->phoneNumbers ?? collect())
                    ->filter(fn($p) => $p && ($p->is_active ?? true))
                    ->sortByDesc(fn($p) => ($p->is_primary ?? false) ? 1 : 0)
                    ->values();

                // Zuerst geschäftliche Nummer suchen
                $phone = $phones->first(fn($p) => in_array(strtoupper($p->phoneType?->code ?? ''), ['BUSINESS', 'WORK'], true));
                // Fallback: private oder mobile Nummer
                if (!$phone) {
                    $phone = $phones->first(fn($p) => in_array(strtoupper($p->phoneType?->code ?? ''), ['PRIVATE', 'HOME', 'MOBILE'], true));
                }
                // Letzter Fallback: irgendeine Nummer
                if (!$phone) {
                    $phone = $phones->first();
                }

                $phoneValue = $phone->full_phone_number
                    ?? $phone->display_number
                    ?? $phone->national
                    ?? $phone->international
                    ?? $phone->raw_input
                    ?? '';
            }

            // E-Mail ermitteln: geschäftlich bevorzugt, dann primäre, dann privat
            $emailValue = '';
            if ($needsEmail && $contact) {
                $emails = ($contact->emailAddresses ?? collect())
                    ->filter(fn($e) => $e && ($e->is_active ?? true))
                    ->sortByDesc(fn($e) => (($e->is_primary ?? false) ? 2 : 0) + (($e->is_verified ?? false) ? 1 : 0))
                    ->values();

                // Zuerst geschäftliche E-Mail suchen
                $email = $emails->first(fn($e) => in_array(strtoupper($e->emailType?->code ?? ''), ['BUSINESS', 'WORK'], true));
                // Fallback: primäre E-Mail
                if (!$email) {
                    $email = $emails->first(fn($e) => $e->is_primary ?? false);
                }
                // Fallback: private E-Mail
                if (!$email) {
                    $email = $emails->first(fn($e) => in_array(strtoupper($e->emailType?->code ?? ''), ['PRIVATE', 'HOME'], true));
                }
                // Letzter Fallback: irgendeine E-Mail
                if (!$email) {
                    $email = $emails->first();
                }

                $emailValue = $email?->email_address ?? '';
            }

            $row = [];

            foreach ($selectedFields as $field) {
                $row[] = match ($field) {
                    'employee_number' => $employee->employee_number,
                    'company_employee_number' => $contactLink?->company_employee_number ?? '',
                    'last_name' => $contact?->last_name ?? '',
                    'first_name' => $contact?->first_name ?? '',
                    'phone' => $phoneValue,
                    'email' => $emailValue,
                    'primary_email' => $contact?->emailAddresses->where('is_primary', true)->first()?->email_address ?? '',
                    'employer' => $employee->employer?->name ?? '',
                    'status' => $employee->is_active ? 'Aktiv' : 'Inaktiv',
                    default => '',
                };
            }

            $csvData[] = $this->escapeCsvRow($row);
        }

        $csvData = "\xEF\xBB\xBF" . implode("\n", $csvData);
        // Exports in geschütztem Storage-Verzeichnis speichern (nicht public)
        Storage::disk('local')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * Custom-Export (basierend auf Template)
     */
    private function exportCustom(HcmExport $export): string
    {
        if (!$export->template) {
            throw new \InvalidArgumentException('Custom Export benötigt ein Template');
        }

        $config = $export->template->configuration;
        // Hier würde die Template-basierte Logik implementiert werden
        
        throw new \RuntimeException('Custom Export noch nicht implementiert');
    }

    /**
     * Mappt Staatsangehörigkeit zu INFONIQA-3-stelligem Code
     */
    private function mapNationalityToCode(?string $nationality): string
    {
        if (!$nationality || trim($nationality) === '') {
            return '000';
        }
        
        $nationality = trim($nationality);
        
        // Wenn bereits numerisch (Code), normalisieren
        if (is_numeric($nationality)) {
            if ($nationality === '0' || $nationality === '00') {
                return '000';
            }
            return str_pad($nationality, 3, '0', STR_PAD_LEFT);
        }
        
        // Mapping von Ländertexten zu 3-stelligen Codes
        $countryMapping = [
            'deutschland' => '000',
            'germany' => '000',
            'de' => '000',
            // Weitere Länder können hier ergänzt werden
            // z.B.:
            // 'polen' => '125',
            // 'poland' => '125',
            // 'türkei' => '163',
            // 'turkey' => '163',
        ];
        
        $nationalityLower = mb_strtolower($nationality);
        
        // Direktes Mapping prüfen
        if (isset($countryMapping[$nationalityLower])) {
            return $countryMapping[$nationalityLower];
        }
        
        // Fallback: Wenn es ein Text ist, den wir nicht kennen, auf "000" setzen
        // (INFONIQA erwartet 3-stellige Codes)
        return '000';
    }

    /**
     * Mappt Konfessionstext zu INFONIQA-Code
     */
    private function mapChurchTaxToCode(?string $churchTax): string
    {
        if (!$churchTax || trim($churchTax) === '') {
            return '';
        }
        
        $churchTax = trim($churchTax);
        $churchTaxLower = mb_strtolower($churchTax);
        
        // Mapping von Konfessionstexten zu Codes
        $mapping = [
            // Altkatholisch
            'altkatholisch' => 'AK',
            'alt-katholisch' => 'AK',
            'alt katholisch' => 'AK',
            
            // Evangelisch
            'evangelisch' => 'EV',
            'ev' => 'EV',
            
            // Freireligiöse Gemeinden
            'freie religionsgemeinschaft alzey' => 'FA',
            'fa' => 'FA',
            'freireligiöse landesgemeinde baden' => 'FB',
            'fb' => 'FB',
            'freireligiöse landesgemeinde pfalz' => 'FG',
            'fg' => 'FG',
            'freireligiöse gemeinde mainz' => 'FM',
            'fm' => 'FM',
            'freireligiöse gemeinde offenbach' => 'FS',
            'freireligiöse gemeinde offenbach/mainz' => 'FS',
            'fs' => 'FS',
            
            // Französisch reformiert
            'französisch reformiert' => 'FR',
            'franzoesisch reformiert' => 'FR',
            'fr' => 'FR',
            
            // Israelitisch/Jüdisch
            'israelitische religionsgemeinschaft baden' => 'IB',
            'ib' => 'IB',
            'jüdische kultussteuer schleswig holstein' => 'IH',
            'ih' => 'IH',
            'israelitische kultussteuer hessen' => 'IL',
            'il' => 'IL',
            'israelitische kultussteuer frankfurt' => 'IS',
            'israelitische bekenntnissteuer bayern' => 'IS',
            'is' => 'IS',
            'israelitische religionsgemeinschaft württemberg' => 'IW',
            'iw' => 'IW',
            'jüdische kultussteuer' => 'JD',
            'jd' => 'JD',
            'jüdische kultussteuer hamburg' => 'JH',
            'jh' => 'JH',
            
            // Evangelisch lutherisch
            'evangelisch lutherisch' => 'LT',
            'lutherisch' => 'LT',
            'lt' => 'LT',
            
            // Neuapostolisch
            'neuapostolisch' => 'NA',
            'na' => 'NA',
            
            // Evangelisch reformiert
            'evangelisch reformiert' => 'RF',
            'rf' => 'RF',
            
            // Römisch-Katholisch
            'römisch-katholisch' => 'RK',
            'roemisch-katholisch' => 'RK',
            'römisch katholisch' => 'RK',
            'roemisch katholisch' => 'RK',
            'katholisch' => 'RK',
            'rk' => 'RK',
        ];
        
        // Direktes Mapping prüfen
        if (isset($mapping[$churchTaxLower])) {
            return $mapping[$churchTaxLower];
        }
        
        // Wenn bereits ein Code (2 Buchstaben), direkt zurückgeben
        if (preg_match('/^[A-Z]{2}$/i', $churchTax)) {
            return strtoupper($churchTax);
        }
        
        // Fallback: Original-Wert zurückgeben (falls kein Mapping gefunden)
        return $churchTax;
    }

    /**
     * Formatiert die Tarifstufe für den Export
     */
    private function formatTariffLevel($tariffLevel): string
    {
        if (!$tariffLevel) {
            return '';
        }

        $candidates = [
            $tariffLevel->code ?? null,
            $tariffLevel->name ?? null,
            $tariffLevel->level ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            if (is_numeric($candidate)) {
                return (string) $candidate;
            }

            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Mappt internes Beschäftigungsverhältnis zu INFONIQA-Format
     */
    private function mapEmploymentRelationshipToInfoniqa(?string $code): string
    {
        if (!$code) {
            return 'Haupt SV-pflichtig'; // Default für leere Werte
        }
        
        $mapping = [
            // Hauptbeschäftigungen (SV-pflichtig)
            'FT' => 'Haupt SV-pflichtig',           // Vollzeit
            'PT' => 'Haupt SV-pflichtig',           // Teilzeit
            'FTB' => 'Haupt SV-pflichtig',          // Vollzeit befristet
            'PTB' => 'Haupt SV-pflichtig',          // Teilzeit befristet
            'AUSB' => 'Haupt SV-pflichtig',         // Ausbildung
            'PRAK' => 'Haupt SV-pflichtig',         // Praktikum
            'WERK' => 'Haupt SV-pflichtig',         // Werkstudent
            'LEIHE' => 'Haupt SV-pflichtig',        // Leiharbeitnehmer
            
            // Nebentätigkeiten
            'MINI' => 'erste Nebentätigkeit GfB',   // Geringfügig entlohnt (Minijob)
            'KURZ' => 'erste Nebentätigkeit GfB',   // Kurzfristige Beschäftigung
            
            // Homeoffice (wird als Hauptbeschäftigung behandelt)
            'HOME' => 'Haupt SV-pflichtig',
        ];
        
        // Direktes Mapping
        if (isset($mapping[$code])) {
            return $mapping[$code];
        }
        
        // Fallback: Standardmäßig als Haupt SV-pflichtig behandeln
        return 'Haupt SV-pflichtig';
    }

    private function formatBankMandant(HcmEmployer $employer): string
    {
        $number = $employer->employer_number;
        $label = trim((string)($employer->display_name ?? ''));

        if ($number !== null && $number !== '') {
            $prefix = str_pad((string)$number, 2, '0', STR_PAD_LEFT);
            return trim($prefix . ' ' . $label);
        }

        return $label;
    }

    private function determineEmployeeRecipient(HcmEmployee $employee): string
    {
        if (!empty($employee->bank_account_holder)) {
            return trim($employee->bank_account_holder);
        }

        $contactLink = $employee->crmContactLinks->first();
        $contact = $contactLink?->contact;

        if ($contact) {
            $fullName = trim(($contact->first_name ? $contact->first_name . ' ' : '') . ($contact->last_name ?? ''));
            if ($fullName !== '') {
                return $fullName;
            }
        }

        if (!empty($employee->alias)) {
            return trim($employee->alias);
        }

        return (string) $employee->employee_number;
    }

    private function formatBankDate($value): string
    {
        if (!$value) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format('d.m.Y');
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function formatIban(?string $iban): string
    {
        if (!$iban) {
            return '';
        }

        $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($iban));
        if (!$normalized) {
            return '';
        }

        return trim(implode(' ', str_split($normalized, 4)));
    }

    private function parseMonetary(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '' || $value === '[leer]') {
            return null;
        }

        $normalized = str_replace(["€", "EUR", "eur", "Â", " a"], '', $value);
        $normalized = preg_replace('/\s+/', '', $normalized);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function formatEuro(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' EUR';
    }

    private function composePurpose(array $parts): string
    {
        $filtered = array_filter(array_map(function ($part) {
            return $part === null ? '' : trim((string)$part);
        }, $parts));

        return implode(' | ', $filtered);
    }

    private function composeHint(array $parts): string
    {
        $filtered = array_filter(array_map(function ($part) {
            return $part === null ? '' : trim((string)$part);
        }, $parts));

        return implode(' | ', $filtered);
    }

    private function truncate(?string $value, int $limit): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim($value);
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
    }

    /**
     * Liefert den Kostenstellencode für einen Vertrag zu einem Stichtag
     */
    private function resolveCostCenterCodeForDate(HcmEmployeeContract $contract, Carbon $targetDate): ?string
    {
        $links = $contract->relationLoaded('costCenterLinks')
            ? $contract->costCenterLinks
            : $contract->costCenterLinks()->with('costCenter')->get();

        $links->loadMissing('costCenter');

        $link = $links
            ->filter(function ($link) use ($targetDate) {
                $startOk = !$link->start_date || $link->start_date->lte($targetDate);
                $endOk = !$link->end_date || $link->end_date->gte($targetDate);
                return $startOk && $endOk;
            })
            ->sort(function ($a, $b) {
                if ($a->is_primary !== $b->is_primary) {
                    return $b->is_primary <=> $a->is_primary;
                }

                $startA = $a->start_date ? $a->start_date->timestamp : 0;
                $startB = $b->start_date ? $b->start_date->timestamp : 0;

                return $startB <=> $startA;
            })
            ->first();

        if ($link && $link->costCenter) {
            $code = $link->costCenter->code ?? '';
            if ($code !== '') {
                return (string) $code;
            }
        }

        if (!empty($contract->cost_center)) {
            return (string) $contract->cost_center;
        }

        return null;
    }

    /**
     * Hilfsmethode: CSV-Zeile escapen
     */
    private function escapeCsvRow(array $row): string
    {
        $escapedRow = [];
        foreach ($row as $field) {
            $field = (string) $field;
            $field = str_replace('"', '""', $field);
            
            if (str_contains($field, ';') || str_contains($field, '"') || 
                str_contains($field, "\n") || str_contains($field, "\r")) {
                $escapedRow[] = '"' . $field . '"';
            } else {
                $escapedRow[] = $field;
            }
        }
        return implode(';', $escapedRow);
    }

    /**
     * Excel-sichere String-Ausgabe: erzwingt Textformat (z.B. ="00123")
     * Verhindert Verlust führender Nullen und wissenschaftliche Notation in Excel
     */
    private function toExcelString(?string $value): string
    {
        $value = (string)($value ?? '');
        if ($value === '') {
            return '';
        }
        // Wenn bereits als ="..." formatiert, zurückgeben
        if (preg_match('/^=\".*\"$/', $value)) {
            return $value;
        }
        return '="' . $value . '"';
    }

    /**
     * Ermittelt die Anzahl der Datensätze im Export
     */
    private function getRecordCount(HcmExport $export, string $filepath, string $disk = 'local'): int
    {
        if (!Storage::disk($disk)->exists($filepath)) {
            return 0;
        }

        $content = Storage::disk($disk)->get($filepath);
        $content = ltrim($content, "\xEF\xBB\xBF");
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);

        if ($content === '') {
            return 0;
        }

        $lines = explode("\n", $content);
        $lineCount = count($lines);

        return match ($export->type) {
            'infoniqa-ma' => max(0, $lineCount - 6),
            'payroll', 'employees' => max(0, $lineCount - 1),
            'infoniqa-dimensions' => $lineCount,
            'infoniqa-bank' => max(0, $lineCount - 5),
            'infoniqa-zeitwirtschaft', 'infoniqa-zeitwirtschaft-monat' => max(0, $lineCount - 1),
            default => max(0, $lineCount),
        };
    }
}
