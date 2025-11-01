<?php

namespace Platform\Hcm\Livewire\Issues;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployeeIssue;
use Platform\Hcm\Models\HcmEmployer;

class Index extends Component
{

    public $search = '';
    public $filterStatus = 'all';
    public $filterType = '';
    public $filterEmployer = '';

    #[Computed]
    public function issues()
    {
        return HcmEmployeeIssue::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('identifier', 'like', '%' . $this->search . '%')
                        ->orWhere('notes', 'like', '%' . $this->search . '%')
                        ->orWhereHas('type', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                        ->orWhereHas('employee', fn($q) => $q->where('employee_number', 'like', '%' . $this->search . '%')
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $this->search . '%']));
                });
            })
            ->when($this->filterStatus === 'issued', fn($q) => $q->whereNotNull('issued_at')->whereNull('returned_at'))
            ->when($this->filterStatus === 'returned', fn($q) => $q->whereNotNull('returned_at'))
            ->when($this->filterStatus === 'pending', fn($q) => $q->whereNull('issued_at'))
            ->when($this->filterType, fn($q) => $q->where('issue_type_id', $this->filterType))
            ->when($this->filterEmployer, fn($q) => $q->whereHas('employee', fn($q2) => $q2->where('employer_id', $this->filterEmployer)))
            ->with(['type', 'contract', 'employee'])
            ->orderBy('issued_at', 'desc')
            ->get();
    }

    #[Computed]
    public function issueTypes()
    {
        return \Platform\Hcm\Models\HcmEmployeeIssueType::where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('name')
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

    public function render()
    {
        return view('hcm::livewire.issues.index')
            ->layout('platform::layouts.app');
    }
}

