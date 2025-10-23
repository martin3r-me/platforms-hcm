<?php

namespace Platform\Hcm\Livewire\TariffAgreement;

use Livewire\Component;
use Platform\Hcm\Models\HcmTariffAgreement;

class Show extends Component
{
    public HcmTariffAgreement $tariffAgreement;

    public function mount(HcmTariffAgreement $tariffAgreement)
    {
        $this->tariffAgreement = $tariffAgreement->load(['tariffGroups.tariffLevels', 'tariffGroups.tariffRates', 'team']);
    }

    public function render()
    {
        return view('hcm::livewire.tariff-agreement.show', [
            'tariffAgreement' => $this->tariffAgreement,
        ])->layout('platform::layouts.app');
    }
}
