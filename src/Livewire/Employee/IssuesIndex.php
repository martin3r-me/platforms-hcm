<?php

namespace Platform\Hcm\Livewire\Employee;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeIssue;

class IssuesIndex extends Component
{

    public HcmEmployee $employee;
    public $search = '';
    public $filterStatus = 'all';
    public $filterType = '';

    public function mount(HcmEmployee $employee)
    {
        $this->employee = $employee;
    }

    #[Computed]
    public function issues()
    {
        return $this->employee->issues()
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('identifier', 'like', '%' . $this->search . '%')
                        ->orWhere('notes', 'like', '%' . $this->search . '%')
                        ->orWhereHas('type', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->filterStatus === 'issued', fn($q) => $q->whereNotNull('issued_at')->whereNull('returned_at'))
            ->when($this->filterStatus === 'returned', fn($q) => $q->whereNotNull('returned_at'))
            ->when($this->filterStatus === 'pending', fn($q) => $q->whereNull('issued_at'))
            ->when($this->filterType, fn($q) => $q->where('issue_type_id', $this->filterType))
            ->with(['type', 'contract'])
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

    public function render()
    {
        return view('hcm::livewire.employee.issues-index')
            ->layout('platform::layouts.app');
    }
}

