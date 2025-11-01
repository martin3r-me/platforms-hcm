<?php

namespace Platform\Hcm\Livewire\Employer;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Models\HcmEmployeeBenefit;

class BenefitsIndex extends Component
{

    public HcmEmployer $employer;
    public $search = '';
    public $filterType = '';
    public $filterActive = 'all';

    public function mount(HcmEmployer $employer)
    {
        $this->employer = $employer;
    }

    #[Computed]
    public function benefits()
    {
        return HcmEmployeeBenefit::where('team_id', $this->employer->team_id)
            ->whereHas('employee', fn($q) => $q->where('employer_id', $this->employer->id))
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('benefit_type', 'like', '%' . $this->search . '%')
                        ->orWhere('insurance_company', 'like', '%' . $this->search . '%')
                        ->orWhere('contract_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('employee', fn($q) => $q->where('employee_number', 'like', '%' . $this->search . '%')
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $this->search . '%']));
                });
            })
            ->when($this->filterType, fn($q) => $q->where('benefit_type', $this->filterType))
            ->when($this->filterActive === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->filterActive === 'inactive', fn($q) => $q->where('is_active', false))
            ->with(['employee', 'contract'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function benefitTypeOptions()
    {
        return [
            'bav' => 'BAV',
            'vwl' => 'VWL',
            'bkv' => 'BKV',
            'jobrad' => 'JobRad',
            'other' => 'Sonstige',
        ];
    }

    public function render()
    {
        return view('hcm::livewire.employer.benefits-index')
            ->layout('platform::layouts.app');
    }
}

