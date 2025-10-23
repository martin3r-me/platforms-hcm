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
            ->forTeam(auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->get();
    }

    public function getTariffGroupsProperty()
    {
        return HcmTariffGroup::with(['tariffAgreement', 'tariffLevels', 'tariffRates'])
            ->forTeam(auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->get();
    }

    public function getTariffLevelsProperty()
    {
        return HcmTariffLevel::with(['tariffGroup.tariffAgreement', 'tariffRates'])
            ->forTeam(auth()->user()->currentTeam->id)
            ->orderBy('code')
            ->get();
    }

    public function getTariffRatesProperty()
    {
        return HcmTariffRate::with(['tariffLevel.tariffGroup.tariffAgreement'])
            ->forTeam(auth()->user()->currentTeam->id)
            ->orderBy('amount', 'desc')
            ->get();
    }

    public function getStatsProperty()
    {
        $teamId = auth()->user()->currentTeam->id;
        
        return [
            'agreements' => HcmTariffAgreement::forTeam($teamId)->count(),
            'groups' => HcmTariffGroup::forTeam($teamId)->count(),
            'levels' => HcmTariffLevel::forTeam($teamId)->count(),
            'rates' => HcmTariffRate::forTeam($teamId)->count(),
        ];
    }

    public function render()
    {
        return view('hcm::livewire.tariff.overview')
            ->layout('platform::layouts.app');
    }
}
