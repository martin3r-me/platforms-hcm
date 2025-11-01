<?php

namespace Platform\Hcm\Livewire\Benefits;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployeeBenefit;
use Platform\Hcm\Models\HcmEmployer;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = '';
    public $filterActive = 'all';
    public $filterEmployer = '';

    #[Computed]
    public function benefits()
    {
        return HcmEmployeeBenefit::where('team_id', auth()->user()->currentTeam->id)
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
            ->when($this->filterEmployer, fn($q) => $q->whereHas('employee', fn($q2) => $q2->where('employer_id', $this->filterEmployer)))
            ->with(['employee', 'contract'])
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

    #[Computed]
    public function employers()
    {
        return HcmEmployer::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();
    }

    public function render()
    {
        return view('hcm::livewire.benefits.index')
            ->layout('platform::layouts.app');
    }
}

