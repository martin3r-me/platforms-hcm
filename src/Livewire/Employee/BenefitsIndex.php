<?php

namespace Platform\Hcm\Livewire\Employee;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeBenefit;

class BenefitsIndex extends Component
{
    use WithPagination;

    public HcmEmployee $employee;
    public $search = '';
    public $filterType = '';
    public $filterActive = 'all';

    public function mount(HcmEmployee $employee)
    {
        $this->employee = $employee;
    }

    #[Computed]
    public function benefits()
    {
        return $this->employee->benefits()
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('benefit_type', 'like', '%' . $this->search . '%')
                        ->orWhere('insurance_company', 'like', '%' . $this->search . '%')
                        ->orWhere('contract_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterType, fn($q) => $q->where('benefit_type', $this->filterType))
            ->when($this->filterActive === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->filterActive === 'inactive', fn($q) => $q->where('is_active', false))
            ->with('contract')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
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
        return view('hcm::livewire.employee.benefits-index');
    }
}

