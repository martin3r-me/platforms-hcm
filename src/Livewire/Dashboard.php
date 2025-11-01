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

        // Durchschnittsalter berechnen
        $employeesForAge = HcmEmployee::active()
            ->forTeam($teamId)
            ->with('crmContactLinks.contact')
            ->get();
        
        $ages = [];
        foreach ($employeesForAge as $employee) {
            $birthDate = $employee->birth_date ?? $employee->crmContactLinks->first()?->contact?->birth_date;
            if ($birthDate) {
                $ages[] = \Carbon\Carbon::parse($birthDate)->age;
            }
        }
        $averageAge = count($ages) > 0 ? round(array_sum($ages) / count($ages), 1) : 0;

        // Durchschnittliche Verweildauer im Betrieb (basierend auf ältestem Vertrag)
        $employeesForTenure = HcmEmployee::active()
            ->forTeam($teamId)
            ->with('contracts')
            ->get();
        
        $tenures = [];
        foreach ($employeesForTenure as $employee) {
            $oldestContract = $employee->contracts->sortBy('start_date')->first();
            if ($oldestContract && $oldestContract->start_date) {
                $tenureInMonths = $oldestContract->start_date->diffInMonths(now());
                $tenures[] = $tenureInMonths;
            }
        }
        $averageTenureMonths = count($tenures) > 0 ? round(array_sum($tenures) / count($tenures), 1) : 0;
        $averageTenureYears = round($averageTenureMonths / 12, 1);

        // Neueinstellungen im letzten Monat
        $newEmployeesLastMonth = HcmEmployee::active()
            ->forTeam($teamId)
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        // Neueinstellungen im letzten Quartal
        $newEmployeesLastQuarter = HcmEmployee::active()
            ->forTeam($teamId)
            ->where('created_at', '>=', now()->subQuarter())
            ->count();

        // Geschlechterverteilung
        $genderDistribution = [
            'm' => 0,
            'w' => 0,
            'd' => 0,
            'unknown' => 0,
        ];
        foreach ($employeesForAge as $employee) {
            $gender = $employee->gender ?? $employee->crmContactLinks->first()?->contact?->gender;
            if ($gender === 'm' || $gender === 'male') {
                $genderDistribution['m']++;
            } elseif ($gender === 'w' || $gender === 'f' || $gender === 'female') {
                $genderDistribution['w']++;
            } elseif ($gender === 'd' || $gender === 'diverse') {
                $genderDistribution['d']++;
            } else {
                $genderDistribution['unknown']++;
            }
        }

        // Mitarbeiter mit Kindern
        $employeesWithChildren = HcmEmployee::active()
            ->forTeam($teamId)
            ->where('children_count', '>', 0)
            ->count();
        
        // Durchschnittliche Anzahl Kinder (nur für Mitarbeiter mit Kindern)
        $totalChildren = HcmEmployee::active()
            ->forTeam($teamId)
            ->where('children_count', '>', 0)
            ->sum('children_count');
        $averageChildrenPerEmployee = $employeesWithChildren > 0 ? round($totalChildren / $employeesWithChildren, 1) : 0;

        // Verweildauer-Verteilung
        $tenureDistribution = [
            'under_1_year' => 0,
            '1_3_years' => 0,
            '3_5_years' => 0,
            '5_10_years' => 0,
            'over_10_years' => 0,
        ];
        foreach ($tenures as $tenureMonths) {
            $tenureYears = $tenureMonths / 12;
            if ($tenureYears < 1) {
                $tenureDistribution['under_1_year']++;
            } elseif ($tenureYears < 3) {
                $tenureDistribution['1_3_years']++;
            } elseif ($tenureYears < 5) {
                $tenureDistribution['3_5_years']++;
            } elseif ($tenureYears < 10) {
                $tenureDistribution['5_10_years']++;
            } else {
                $tenureDistribution['over_10_years']++;
            }
        }

        // Tätigkeitsprofile-Verteilung (basierend auf aktiven Verträgen)
        $activeContractsWithActivities = \Platform\Hcm\Models\HcmEmployeeContract::where('team_id', $teamId)
            ->where('is_active', true)
            ->where(function($q) {
                $today = now()->toDateString();
                $q->where(function($q2) use ($today) {
                    $q2->whereNull('start_date')->orWhere('start_date', '<=', $today);
                })
                ->where(function($q2) use ($today) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', $today);
                });
            })
            ->whereNotNull('primary_job_activity_id')
            ->with('primaryJobActivity', 'jobActivityAlias')
            ->get();
        
        $activityDistribution = [];
        foreach ($activeContractsWithActivities as $contract) {
            $activityName = $contract->primary_job_activity_display_name ?? 'Unbekannt';
            if (!isset($activityDistribution[$activityName])) {
                $activityDistribution[$activityName] = 0;
            }
            $activityDistribution[$activityName]++;
        }
        arsort($activityDistribution);
        $topJobActivities = array_slice($activityDistribution, 0, 10, true); // Top 10

        // Altersverteilung (Kategorien)
        $ageDistribution = [
            'under_25' => 0,
            '25_35' => 0,
            '35_45' => 0,
            '45_55' => 0,
            'over_55' => 0,
        ];
        foreach ($ages as $age) {
            if ($age < 25) {
                $ageDistribution['under_25']++;
            } elseif ($age < 35) {
                $ageDistribution['25_35']++;
            } elseif ($age < 45) {
                $ageDistribution['35_45']++;
            } elseif ($age < 55) {
                $ageDistribution['45_55']++;
            } else {
                $ageDistribution['over_55']++;
            }
        }

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
            'averageAge' => $averageAge,
            'averageTenureYears' => $averageTenureYears,
            'averageTenureMonths' => $averageTenureMonths,
            'newEmployeesLastMonth' => $newEmployeesLastMonth,
            'newEmployeesLastQuarter' => $newEmployeesLastQuarter,
            'genderDistribution' => $genderDistribution,
            'employeesWithChildren' => $employeesWithChildren,
            'averageChildrenPerEmployee' => $averageChildrenPerEmployee,
            'tenureDistribution' => $tenureDistribution,
            'topJobActivities' => $topJobActivities,
            'totalActiveContracts' => $activeContractsWithActivities->count(),
            'ageDistribution' => $ageDistribution,
        ])->layout('platform::layouts.app');
    }
}