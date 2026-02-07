<?php

namespace Platform\Hcm\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmApplicant;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;

class Sidebar extends Component
{
    #[Computed]
    public function recentEmployees()
    {
        return HcmEmployee::with(['crmContactLinks.contact'])
            ->forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentEmployers()
    {
        return HcmEmployer::with(['organizationCompanyLinks.company'])
            ->forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentApplicants()
    {
        return HcmApplicant::with(['crmContactLinks.contact', 'applicantStatus'])
            ->forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;

        return [
            'total_employees' => HcmEmployee::forTeam($teamId)->count(),
            'active_employees' => HcmEmployee::forTeam($teamId)->active()->count(),
            'total_employers' => HcmEmployer::forTeam($teamId)->count(),
            'active_employers' => HcmEmployer::forTeam($teamId)->active()->count(),
            'total_applicants' => HcmApplicant::forTeam($teamId)->count(),
            'active_applicants' => HcmApplicant::forTeam($teamId)->active()->count(),
        ];
    }

    public function render()
    {
        return view('hcm::livewire.sidebar');
    }
}