<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Models\HcmTariffGroup;
use Platform\Hcm\Models\HcmTariffLevel;
use Carbon\Carbon;

class EmployeeTariffAssignmentService
{
    /**
     * Assign employee to tariff group and level
     */
    public function assignTariff(
        HcmEmployeeContract $contract,
        string $tariffGroupCode,
        string $tariffLevelCode,
        ?string $assignmentDate = null
    ): bool {
        $assignmentDate = $assignmentDate ?? now()->toDateString();

        // Find tariff group and level
        $tariffGroup = HcmTariffGroup::where('code', $tariffGroupCode)->first();
        $tariffLevel = HcmTariffLevel::where('code', $tariffLevelCode)
            ->where('tariff_group_id', $tariffGroup->id)
            ->first();

        if (!$tariffGroup || !$tariffLevel) {
            throw new \Exception("Tariff group '{$tariffGroupCode}' or level '{$tariffLevelCode}' not found");
        }

        // Calculate next progression date
        $nextProgressionDate = $this->calculateNextProgressionDate($tariffLevel, $assignmentDate);

        // Update contract
        $contract->update([
            'tariff_group_id' => $tariffGroup->id,
            'tariff_level_id' => $tariffLevel->id,
            'tariff_assignment_date' => $assignmentDate,
            'tariff_level_start_date' => $assignmentDate,
            'next_tariff_level_date' => $nextProgressionDate,
        ]);

        return true;
    }

    /**
     * Bulk assign multiple employees to tariff groups
     */
    public function bulkAssignTariffs(array $assignments): array
    {
        $results = [
            'processed' => 0,
            'assigned' => 0,
            'errors' => []
        ];

        foreach ($assignments as $assignment) {
            try {
                $contract = HcmEmployeeContract::find($assignment['contract_id']);
                if (!$contract) {
                    throw new \Exception("Contract not found: {$assignment['contract_id']}");
                }

                $this->assignTariff(
                    $contract,
                    $assignment['tariff_group_code'],
                    $assignment['tariff_level_code'],
                    $assignment['assignment_date'] ?? null
                );

                $results['assigned']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'assignment' => $assignment,
                    'error' => $e->getMessage()
                ];
            }
            $results['processed']++;
        }

        return $results;
    }

    /**
     * Get employees without tariff assignment
     */
    public function getUnassignedEmployees(): \Illuminate\Database\Eloquent\Collection
    {
        return HcmEmployeeContract::with(['employee', 'tariffGroup', 'tariffLevel'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('tariff_group_id')
                      ->orWhereNull('tariff_level_id');
            })
            ->get();
    }

    /**
     * Get employees with tariff assignment
     */
    public function getAssignedEmployees(): \Illuminate\Database\Eloquent\Collection
    {
        return HcmEmployeeContract::with(['employee', 'tariffGroup', 'tariffLevel'])
            ->where('is_active', true)
            ->whereNotNull('tariff_group_id')
            ->whereNotNull('tariff_level_id')
            ->get();
    }

    /**
     * Calculate next progression date for a tariff level
     */
    private function calculateNextProgressionDate(HcmTariffLevel $level, string $startDate): ?string
    {
        if ($level->progression_months === 999) {
            return null; // Final level
        }

        return Carbon::parse($startDate)
            ->addMonths($level->progression_months)
            ->toDateString();
    }

    /**
     * Import employee tariff assignments from CSV
     */
    public function importAssignmentsFromCsv(string $filePath): array
    {
        $csv = \League\Csv\Reader::createFromPath($filePath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $assignments = [];
        foreach ($csv as $record) {
            $assignments[] = [
                'employee_number' => $record['AbrechNr'] ?? '',
                'tariff_group_code' => $record['TKZ'] ?? '',
                'tariff_level_code' => $record['Stufe'] ?? '',
                'assignment_date' => $this->parseDate($record['Datum'] ?? ''),
                'first_name' => $record['Vorname'] ?? '',
                'last_name' => $record['Name'] ?? '',
            ];
        }

        return $this->processCsvAssignments($assignments);
    }

    /**
     * Parse German date format (DD.MM.YYYY) to YYYY-MM-DD
     */
    private function parseDate(string $date): string
    {
        if (empty($date)) {
            return now()->toDateString();
        }

        try {
            return \Carbon\Carbon::createFromFormat('d.m.Y', $date)->toDateString();
        } catch (\Exception $e) {
            return now()->toDateString();
        }
    }

    /**
     * Process CSV assignments
     */
    private function processCsvAssignments(array $assignments): array
    {
        $results = [
            'processed' => 0,
            'assigned' => 0,
            'errors' => []
        ];

        foreach ($assignments as $assignment) {
            try {
                // Handle AT employees (empty level)
                if ($assignment['tariff_group_code'] === 'AT' && empty($assignment['tariff_level_code'])) {
                    $assignment['tariff_level_code'] = '1'; // Default AT level
                }

                // Find employee by number
                $employee = \Platform\Hcm\Models\HcmEmployee::where('employee_number', $assignment['employee_number'])->first();
                if (!$employee) {
                    throw new \Exception("Employee not found: {$assignment['employee_number']} ({$assignment['first_name']} {$assignment['last_name']})");
                }

                // Get active contract
                $contract = $employee->activeContract();
                if (!$contract) {
                    throw new \Exception("No active contract for employee: {$assignment['employee_number']}");
                }

                $this->assignTariff(
                    $contract,
                    $assignment['tariff_group_code'],
                    $assignment['tariff_level_code'],
                    $assignment['assignment_date']
                );

                $results['assigned']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'assignment' => $assignment,
                    'error' => $e->getMessage()
                ];
            }
            $results['processed']++;
        }

        return $results;
    }
}
