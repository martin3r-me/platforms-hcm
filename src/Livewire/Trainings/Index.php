<?php

namespace Platform\Hcm\Livewire\Trainings;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployeeTraining;
use Platform\Hcm\Models\HcmEmployer;

class Index extends Component
{

    public $search = '';
    public $filterEmployer = '';
    public $filterType = '';
    public $filterStatus = 'all';

    #[Computed]
    public function trainings()
    {
        return HcmEmployeeTraining::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('provider', 'like', '%' . $this->search . '%')
                        ->orWhere('notes', 'like', '%' . $this->search . '%')
                        ->orWhereHas('trainingType', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                        ->orWhereHas('employee', fn($q) => $q->where('employee_number', 'like', '%' . $this->search . '%')
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $this->search . '%']));
                });
            })
            ->when($this->filterEmployer, fn($q) => $q->whereHas('employee', fn($q2) => $q2->where('employer_id', $this->filterEmployer)))
            ->when($this->filterType, fn($q) => $q->where('training_type_id', $this->filterType))
            ->when($this->filterStatus === 'completed', fn($q) => $q->where('status', 'completed'))
            ->when($this->filterStatus === 'pending', fn($q) => $q->where('status', 'pending'))
            ->when($this->filterStatus === 'expired', fn($q) => $q->where('valid_until', '<', now()))
            ->with(['employee.crmContactLinks.contact', 'contract', 'trainingType'])
            ->orderBy('completed_date', 'desc')
            ->get();
    }

    #[Computed]
    public function employers()
    {
        return HcmEmployer::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('employer_number')
            ->get()
            ->sortBy('display_name')
            ->values();
    }

    #[Computed]
    public function trainingTypes()
    {
        return \Platform\Hcm\Models\HcmEmployeeTrainingType::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('hcm::livewire.trainings.index')
            ->layout('platform::layouts.app');
    }
}

