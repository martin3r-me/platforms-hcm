<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;

class HcmLookupService
{
    /**
     * Arbeitnehmer als Select-Optionen: [['id' => ..., 'label' => 'Max Mustermann (1001)']]
     * Nutzt HasEmployeeContact-Trait → crmContactLinks → CrmContact → full_name
     */
    public function employeesForSelect(int $teamId, ?int $employerId = null): array
    {
        return HcmEmployee::where('team_id', $teamId)
            ->when($employerId, fn($q) => $q->where('employer_id', $employerId))
            ->with('crmContactLinks.contact')
            ->orderBy('employee_number')
            ->get()
            ->map(function ($employee) {
                $name = $employee->full_name;
                $nr = $employee->employee_number;
                return [
                    'id' => $employee->id,
                    'label' => $name ? "{$name} ({$nr})" : $nr,
                ];
            })
            ->toArray();
    }

    /**
     * Arbeitgeber als Select-Optionen: [['id' => ..., 'label' => 'Firma GmbH']]
     */
    public function employersForSelect(int $teamId): array
    {
        return HcmEmployer::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('employer_number')
            ->get()
            ->sortBy('display_name')
            ->values()
            ->map(fn($e) => [
                'id' => $e->id,
                'label' => $e->display_name,
            ])
            ->toArray();
    }
}
