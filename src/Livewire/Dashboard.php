<?php

namespace Platform\Hcm\Livewire;

use Livewire\Component;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;

class Dashboard extends Component
{
    public $perspective = 'team';

    public function render()
    {
        $teamId = auth()->user()->currentTeam->id;
        
        // Basis-Statistiken über HCM Interface - mit Team-Filter
        $totalEmployees = HcmEmployee::active()->forTeam($teamId)->count();
        $employeesWithContacts = HcmEmployee::active()
            ->forTeam($teamId)
            ->whereHas('crmContactLinks')
            ->count();
        $employeesWithoutContacts = $totalEmployees - $employeesWithContacts;

        // Arbeitgeber-Statistiken (neue Struktur) - mit Team-Filter
        $totalEmployers = HcmEmployer::active()->forTeam($teamId)->count();
        $employersWithEmployees = HcmEmployer::active()
            ->forTeam($teamId)
            ->whereHas('employees')
            ->count();

        // Top Arbeitgeber nach Mitarbeiter-Anzahl - mit Team-Filter
        $topEmployersByEmployees = HcmEmployer::active()
            ->forTeam($teamId)
            ->withCount('employees')
            ->orderByDesc('employees_count')
            ->take(5)
            ->get();

        // Neueste Mitarbeiter - mit Team-Filter
        $recentEmployees = HcmEmployee::active()
            ->forTeam($teamId)
            ->with(['employer', 'crmContactLinks.contact'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Mitarbeiter mit Mitarbeiter-Nummern (employee_number) - mit Team-Filter
        $employeesWithNumbers = HcmEmployee::active()
            ->forTeam($teamId)
            ->whereNotNull('employee_number')
            ->where('employee_number', '!=', '')
            ->count();

        // Mitarbeiter ohne Mitarbeiter-Nummern
        $employeesWithoutNumbers = $totalEmployees - $employeesWithNumbers;

        return view('hcm::livewire.dashboard', [
            'totalEmployees' => $totalEmployees,
            'employeesWithContacts' => $employeesWithContacts,
            'employeesWithoutContacts' => $employeesWithoutContacts,
            'totalEmployers' => $totalEmployers,
            'employersWithEmployees' => $employersWithEmployees,
            'topEmployersByEmployees' => $topEmployersByEmployees,
            'recentEmployees' => $recentEmployees,
            'employeesWithCompanyNumbers' => $employeesWithNumbers,
            'employeesWithoutCompanyNumbers' => $employeesWithoutNumbers,
        ])->layout('platform::layouts.app');
    }
}