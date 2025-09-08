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
        // Basis-Statistiken über HCM Interface
        $totalEmployees = HcmEmployee::active()->count();
        $employeesWithContacts = HcmEmployee::active()
            ->whereHas('crmContactLinks')
            ->count();
        $employeesWithoutContacts = $totalEmployees - $employeesWithContacts;

        // Arbeitgeber-Statistiken (neue Struktur)
        $totalEmployers = HcmEmployer::active()->count();
        $employersWithEmployees = HcmEmployer::active()
            ->whereHas('employees')
            ->count();

        // Top Arbeitgeber nach Mitarbeiter-Anzahl
        $topEmployersByEmployees = HcmEmployer::active()
            ->withCount('employees')
            ->orderByDesc('employees_count')
            ->take(5)
            ->get();

        // Neueste Mitarbeiter
        $recentEmployees = HcmEmployee::active()
            ->with(['employer', 'crmContactLinks.contact'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Mitarbeiter mit Mitarbeiter-Nummern (employee_number)
        $employeesWithNumbers = HcmEmployee::active()
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