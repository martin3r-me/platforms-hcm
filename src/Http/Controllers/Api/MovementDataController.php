<?php

namespace Platform\Hcm\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Models\HcmAbsenceReason;
use Platform\Hcm\Models\HcmContractTimeRecord;
use Platform\Hcm\Models\HcmContractVacationDay;
use Platform\Hcm\Models\HcmContractAbsenceDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * API Controller für Bewegungsdaten aus Nostradamus
 * 
 * Empfängt Bewegungsdaten (Stempelzeiten, Urlaubstage, Abwesenheitstage) 
 * und speichert sie in den entsprechenden HCM-Tabellen.
 */
class MovementDataController extends ApiController
{
    /**
     * POST /api/hcm/movements
     * 
     * Empfängt Bewegungsdaten aus Nostradamus und speichert sie.
     * 
     * Erwartetes Format:
     * {
     *   "employer_uuid": "uuid",
     *   "date": "2025-01-11",  // Optional: Default = heute
     *   "data": [
     *     {
     *       "employee_number": "12345",
     *       "type": "time_record|vacation_day|absence_day",
     *       // Für time_record:
     *       "clock_in": "08:00",
     *       "clock_out": "17:00",
     *       "break_start": "12:00",
     *       "break_end": "13:00",
     *       // Für vacation_day:
     *       "vacation_type": "full_day|half_day_morning|half_day_afternoon",
     *       // Für absence_day:
     *       "absence_reason_code": "SICK",
     *       "absence_type": "full_day|half_day_morning|half_day_afternoon",
     *       "has_sick_note": false,
     *       "sick_note_from": "2025-01-11",
     *       "sick_note_until": "2025-01-13"
     *     }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        try {
            // Basis-Validierung
            $validator = Validator::make($request->all(), [
                'employer_uuid' => 'required|string',
                'date' => 'nullable|date',
                'data' => 'required|array|min:1',
                'data.*.employee_number' => 'required|string',
                'data.*.type' => 'required|string|in:time_record,vacation_day,absence_day',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $employerUuid = $request->input('employer_uuid');
            $targetDate = $request->input('date', now()->toDateString());
            $data = $request->input('data', []);

            // Employer finden
            $employer = HcmEmployer::where('uuid', $employerUuid)->first();
            if (!$employer) {
                return $this->error('Employer nicht gefunden', null, 404);
            }

            $results = [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => [],
            ];

            // Jeden Datensatz verarbeiten
            foreach ($data as $index => $item) {
                try {
                    $result = $this->processMovementItem($employer, $targetDate, $item, $index);
                    $results['processed']++;
                    if ($result['created']) {
                        $results['created']++;
                    } elseif ($result['updated']) {
                        $results['updated']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'index' => $index,
                        'employee_number' => $item['employee_number'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return $this->success([
                'date' => $targetDate,
                'employer_uuid' => $employerUuid,
                'statistics' => $results,
            ], 'Bewegungsdaten verarbeitet');

        } catch (\Exception $e) {
            return $this->error('Fehler beim Verarbeiten der Bewegungsdaten: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Verarbeitet einen einzelnen Bewegungsdatensatz
     * 
     * Team/Arbeitgeber-Zuordnung:
     * - Employee wird über employer_id + employee_number gefunden (employer_uuid kommt im Request)
     * - Contract wird über activeContract() geholt (neuester aktiver Contract für das Datum)
     * - Contract.team_id wird verwendet für die Zuordnung
     * 
     * Falls ein Employee mehrere aktive Contracts hat, wird der neueste verwendet.
     */
    protected function processMovementItem($employer, string $date, array $item, int $index): array
    {
        $employeeNumber = $item['employee_number'];
        $type = $item['type'];

        // Employee finden - über employer_id (aus employer_uuid) + employee_number
        // Das stellt sicher, dass der Employee zum richtigen Arbeitgeber gehört
        $employee = HcmEmployee::where('employer_id', $employer->id)
            ->where('employee_number', $employeeNumber)
            ->first();

        if (!$employee) {
            throw new \Exception("Employee mit Nummer '{$employeeNumber}' nicht gefunden für Employer '{$employer->uuid}'");
        }

        // Aktiven Contract finden - für das spezifische Datum
        // activeContract() holt den neuesten aktiven Contract, der am angegebenen Datum gültig war
        $contract = $employee->activeContract();
        if (!$contract) {
            throw new \Exception("Kein aktiver Vertrag für Employee '{$employeeNumber}' am Datum '{$date}' gefunden");
        }
        
        // Team-ID kommt vom Contract (contract.team_id)
        // Das stellt sicher, dass die Bewegungsdaten zum richtigen Team gehören

        $result = ['created' => false, 'updated' => false];

        switch ($type) {
            case 'time_record':
                $result = $this->processTimeRecord($contract, $employee, $date, $item);
                break;
            case 'vacation_day':
                $result = $this->processVacationDay($contract, $employee, $date, $item);
                break;
            case 'absence_day':
                $result = $this->processAbsenceDay($contract, $employee, $date, $item);
                break;
            default:
                throw new \Exception("Unbekannter Typ: {$type}");
        }

        return $result;
    }

    /**
     * Verarbeitet Stempelzeit-Daten
     */
    protected function processTimeRecord($contract, $employee, string $date, array $item): array
    {
        $validator = Validator::make($item, [
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0',
            'work_minutes' => 'nullable|integer|min:0',
            'status' => 'nullable|in:draft,confirmed,rejected,corrected',
            'source_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception('Validierungsfehler: ' . $validator->errors()->first());
        }

        $data = [
            'contract_id' => $contract->id,
            'employee_id' => $employee->id,
            'team_id' => $contract->team_id,
            'record_date' => $date,
            'clock_in' => $item['clock_in'] ?? null,
            'clock_out' => $item['clock_out'] ?? null,
            'break_start' => $item['break_start'] ?? null,
            'break_end' => $item['break_end'] ?? null,
            'break_minutes' => $item['break_minutes'] ?? 0,
            'work_minutes' => $item['work_minutes'] ?? null,
            'status' => $item['status'] ?? 'confirmed',
            'source' => 'push',
            'source_reference' => $item['source_reference'] ?? null,
            'notes' => $item['notes'] ?? null,
            'metadata' => $item['metadata'] ?? null,
            'created_by_user_id' => auth()->id(),
        ];

        // Berechne work_minutes falls nicht gesetzt UND clock_out vorhanden
        // Wenn clock_out fehlt, sollte work_minutes nur gesetzt werden, wenn es explizit aus der Quelle kommt
        if (empty($data['work_minutes']) && $data['clock_in'] && $data['clock_out']) {
            $clockIn = Carbon::parse($date . ' ' . $data['clock_in']);
            $clockOut = Carbon::parse($date . ' ' . $data['clock_out']);
            $totalMinutes = $clockOut->diffInMinutes($clockIn);
            $data['work_minutes'] = max(0, $totalMinutes - $data['break_minutes']);
        }
        
        // Wenn clock_out fehlt, aber work_minutes gesetzt ist, validiere das
        // (kann vorkommen bei unvollständigen Stempelungen, z.B. nur Einstempeln)
        if (!$data['clock_out'] && $data['work_minutes']) {
            // work_minutes ist gesetzt, aber clock_out fehlt - das ist OK für unvollständige Stempelungen
            // Aber wir sollten sicherstellen, dass es nicht zu hoch ist (z.B. max 24h = 1440 Minuten)
            if ($data['work_minutes'] > 1440) {
                $data['work_minutes'] = null; // Ungültiger Wert, entfernen
            }
        }

        $record = HcmContractTimeRecord::updateOrCreate(
            [
                'contract_id' => $contract->id,
                'record_date' => $date,
            ],
            $data
        );

        return [
            'created' => $record->wasRecentlyCreated,
            'updated' => !$record->wasRecentlyCreated,
        ];
    }

    /**
     * Verarbeitet Urlaubstag-Daten
     */
    protected function processVacationDay($contract, $employee, string $date, array $item): array
    {
        $validator = Validator::make($item, [
            'vacation_type' => 'nullable|in:full_day,half_day_morning,half_day_afternoon',
            'status' => 'nullable|in:requested,approved,rejected,cancelled',
            'source_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception('Validierungsfehler: ' . $validator->errors()->first());
        }

        $data = [
            'contract_id' => $contract->id,
            'employee_id' => $employee->id,
            'team_id' => $contract->team_id,
            'vacation_date' => $date,
            'type' => $item['vacation_type'] ?? 'full_day',
            'status' => $item['status'] ?? 'approved',
            'source' => 'push',
            'source_reference' => $item['source_reference'] ?? null,
            'notes' => $item['notes'] ?? null,
            'metadata' => $item['metadata'] ?? null,
            'created_by_user_id' => auth()->id(),
        ];

        // Wenn status = approved, setze approved_by und approved_at
        if ($data['status'] === 'approved') {
            $data['approved_by_user_id'] = auth()->id();
            $data['approved_at'] = now();
        }

        $vacationDay = HcmContractVacationDay::updateOrCreate(
            [
                'contract_id' => $contract->id,
                'vacation_date' => $date,
            ],
            $data
        );

        return [
            'created' => $vacationDay->wasRecentlyCreated,
            'updated' => !$vacationDay->wasRecentlyCreated,
        ];
    }

    /**
     * Verarbeitet Abwesenheitstag-Daten
     */
    protected function processAbsenceDay($contract, $employee, string $date, array $item): array
    {
        $validator = Validator::make($item, [
            'absence_reason_code' => 'required|string',
            'absence_type' => 'nullable|in:full_day,half_day_morning,half_day_afternoon',
            'has_sick_note' => 'nullable|boolean',
            'sick_note_from' => 'nullable|date',
            'sick_note_until' => 'nullable|date|after_or_equal:sick_note_from',
            'sick_note_number' => 'nullable|string',
            'status' => 'nullable|in:reported,confirmed,rejected,cancelled',
            'source_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception('Validierungsfehler: ' . $validator->errors()->first());
        }

        // Abwesenheitsgrund finden
        $absenceReason = HcmAbsenceReason::where('team_id', $contract->team_id)
            ->where('code', $item['absence_reason_code'])
            ->first();

        if (!$absenceReason) {
            throw new \Exception("Abwesenheitsgrund mit Code '{$item['absence_reason_code']}' nicht gefunden");
        }

        $data = [
            'contract_id' => $contract->id,
            'employee_id' => $employee->id,
            'team_id' => $contract->team_id,
            'absence_date' => $date,
            'type' => $item['absence_type'] ?? 'full_day',
            'absence_reason_id' => $absenceReason->id,
            'reason_custom' => $item['reason_custom'] ?? null,
            'has_sick_note' => $item['has_sick_note'] ?? false,
            'sick_note_from' => $item['sick_note_from'] ?? null,
            'sick_note_until' => $item['sick_note_until'] ?? null,
            'sick_note_number' => $item['sick_note_number'] ?? null,
            'status' => $item['status'] ?? 'confirmed',
            'source' => 'push',
            'source_reference' => $item['source_reference'] ?? null,
            'source_synced_at' => now(),
            'notes' => $item['notes'] ?? null,
            'metadata' => $item['metadata'] ?? null,
            'created_by_user_id' => auth()->id(),
        ];

        // Wenn status = confirmed, setze confirmed_by und confirmed_at
        if ($data['status'] === 'confirmed') {
            $data['confirmed_by_user_id'] = auth()->id();
            $data['confirmed_at'] = now();
        }

        $absenceDay = HcmContractAbsenceDay::updateOrCreate(
            [
                'contract_id' => $contract->id,
                'absence_date' => $date,
            ],
            $data
        );

        return [
            'created' => $absenceDay->wasRecentlyCreated,
            'updated' => !$absenceDay->wasRecentlyCreated,
        ];
    }
}
