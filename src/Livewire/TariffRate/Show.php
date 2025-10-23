<?php

namespace Platform\Hcm\Livewire\TariffRate;

use Livewire\Component;
use Platform\Hcm\Models\HcmTariffRate;

class Show extends Component
{
    public HcmTariffRate $tariffRate;

    public function mount(HcmTariffRate $tariffRate)
    {
        $this->tariffRate = $tariffRate->load([
            'tariffLevel.tariffGroup.tariffAgreement'
        ]);
    }

    public function render()
    {
        return view('hcm::livewire.tariff-rate.show');
    }
}