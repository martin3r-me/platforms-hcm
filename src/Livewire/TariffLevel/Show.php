<?php

namespace Platform\Hcm\Livewire\TariffLevel;

use Livewire\Component;
use Platform\Hcm\Models\HcmTariffLevel;

class Show extends Component
{
    public HcmTariffLevel $tariffLevel;

    public function mount(HcmTariffLevel $tariffLevel)
    {
        $this->tariffLevel = $tariffLevel->load(['tariffGroup.tariffAgreement', 'tariffRates']);
    }

    public function render()
    {
        return view('hcm::livewire.tariff-level.show', [
            'tariffLevel' => $this->tariffLevel,
        ])->layout('platform::layouts.app');
    }
}
