<?php

namespace Platform\Hcm\Livewire\Tariff;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmTariffAgreement;
use Platform\Hcm\Models\HcmTariffGroup;
use Platform\Hcm\Models\HcmTariffLevel;
use Platform\Hcm\Models\HcmTariffRate;

class Overview extends Component
{
    public function getTariffAgreementsProperty()
    {
        return HcmTariffAgreement::with(['tariffGroups.tariffLevels', 'tariffGroups.tariffRates'])
            ->where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->get();
    }

    public function getTariffGroupsProperty()
    {
        return HcmTariffGroup::with(['tariffAgreement', 'tariffLevels', 'tariffRates'])
            ->whereHas('tariffAgreement', function($query) {
                $query->where('team_id', auth()->user()->currentTeam->id);
            })
            ->orderBy('name')
            ->get();
    }

    public function getTariffLevelsProperty()
    {
        return HcmTariffLevel::with(['tariffGroup.tariffAgreement', 'tariffRates'])
            ->whereHas('tariffGroup.tariffAgreement', function($query) {
                $query->where('team_id', auth()->user()->currentTeam->id);
            })
            ->orderBy('code')
            ->get();
    }

    public function getTariffRatesProperty()
    {
        return HcmTariffRate::with(['tariffLevel.tariffGroup.tariffAgreement'])
            ->whereHas('tariffLevel.tariffGroup.tariffAgreement', function($query) {
                $query->where('team_id', auth()->user()->currentTeam->id);
            })
            ->orderBy('amount', 'desc')
            ->get();
    }

    public function getStatsProperty()
    {
        $teamId = auth()->user()->currentTeam->id;
        
        return [
            'agreements' => HcmTariffAgreement::where('team_id', $teamId)->count(),
            'groups' => HcmTariffGroup::whereHas('tariffAgreement', function($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })->count(),
            'levels' => HcmTariffLevel::whereHas('tariffGroup.tariffAgreement', function($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })->count(),
            'rates' => HcmTariffRate::whereHas('tariffLevel.tariffGroup.tariffAgreement', function($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })->count(),
        ];
    }

    public function render()
    {
        return view('hcm::livewire.tariff.overview')
            ->layout('platform::layouts.app');
    }
}
