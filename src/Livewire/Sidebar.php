<?php

namespace Platform\Hcm\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Core\Models\Team;
use Platform\Hcm\Models\HcmApplicant;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;

class Sidebar extends Component
{
    #[Computed]
    public function recentEmployees()
    {
        $teamId = auth()->user()->currentTeam->id;
        $allowedTeamIds = $this->getAllowedTeamIds($teamId);

        return HcmEmployee::with([
            'crmContactLinks' => fn ($q) => $q->whereIn('team_id', $allowedTeamIds),
            'crmContactLinks.contact',
        ])
            ->forTeam($teamId)
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
        $teamId = auth()->user()->currentTeam->id;
        $allowedTeamIds = $this->getAllowedTeamIds($teamId);

        return HcmApplicant::with([
            'crmContactLinks' => fn ($q) => $q->whereIn('team_id', $allowedTeamIds),
            'crmContactLinks.contact',
            'applicantStatus',
        ])
            ->forTeam($teamId)
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

    private function getAllowedTeamIds(int $teamId): array
    {
        $team = Team::find($teamId);
        if (!$team) {
            return [$teamId];
        }

        return array_merge([$teamId], $team->getAllAncestors()->pluck('id')->all());
    }
}