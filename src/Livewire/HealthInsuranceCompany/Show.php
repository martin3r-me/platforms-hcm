<?php

namespace Platform\Hcm\Livewire\HealthInsuranceCompany;

use Livewire\Component;
use Platform\Hcm\Models\HcmHealthInsuranceCompany;

class Show extends Component
{
    public HcmHealthInsuranceCompany $healthInsuranceCompany;

    public function mount(HcmHealthInsuranceCompany $healthInsuranceCompany)
    {
        $this->healthInsuranceCompany = $healthInsuranceCompany->load([
            'employees.contracts' => function ($query) {
                $query->where('is_active', true);
            }
        ]);
    }

    public function render()
    {
        return view('hcm::livewire.health-insurance-company.show')
            ->layout('platform::layouts.app');
    }
}
