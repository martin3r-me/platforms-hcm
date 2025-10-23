<?php

namespace Platform\Hcm\Livewire\TariffGroup;

use Livewire\Component;
use Platform\Hcm\Models\HcmTariffGroup;

class Show extends Component
{
    public HcmTariffGroup $tariffGroup;

    public function mount(HcmTariffGroup $tariffGroup)
    {
        $this->tariffGroup = $tariffGroup->load(['tariffAgreement', 'tariffLevels', 'tariffRates']);
    }

    public function render()
    {
        return view('hcm::livewire.tariff-group.show', [
            'tariffGroup' => $this->tariffGroup,
        ])->layout('platform::layouts.app');
    }
}
