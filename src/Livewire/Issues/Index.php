<?php

namespace Platform\Hcm\Livewire\Issues;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Hcm\Models\HcmEmployeeIssue;
use Platform\Hcm\Models\HcmEmployeeIssueType;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;

class Index extends Component
{

    public $search = '';
    public $filterStatus = 'all';
    public $filterType = '';
    public $filterEmployer = '';
    
    // Modal state
    public $showModal = false;
    public $editingIssue = null;
    
    // Form fields
    public $employee_id = '';
    public $issue_type_id = '';
    public $title = '';
    public $description = '';
    public $identifier = '';
    public $issued_at = '';
    public $returned_at = '';
    public $notes = '';
    public $metadata = [];
    
    protected $listeners = ['open-create-issue-modal' => 'openCreateModal', 'edit-issue' => 'openEditModal'];

    #[Computed]
    public function issues()
    {
        return HcmEmployeeIssue::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('identifier', 'like', '%' . $this->search . '%')
                        ->orWhere('title', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%')
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
    
    #[Computed]
    public function employees()
    {
        return HcmEmployee::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->filterEmployer, fn($q) => $q->where('employer_id', $this->filterEmployer))
            ->orderBy('employee_number')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'label' => ($employee->getContact()?->full_name ?? $employee->employee_number) . ' (' . $employee->employee_number . ')'
                ];
            });
    }
    
    #[Computed]
    public function selectedIssueType()
    {
        if (!$this->issue_type_id) {
            return null;
        }
        return HcmEmployeeIssueType::find($this->issue_type_id);
    }
    
    public function openCreateModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }
    
    public function openEditModal($id)
    {
        $issue = HcmEmployeeIssue::find($id);
        if (!$issue) {
            return;
        }
        
        $this->editingIssue = $issue;
        $this->employee_id = $issue->employee_id;
        $this->issue_type_id = $issue->issue_type_id;
        $this->title = $issue->title;
        $this->description = $issue->description;
        $this->identifier = $issue->identifier;
        $this->issued_at = $issue->issued_at?->format('Y-m-d');
        $this->returned_at = $issue->returned_at?->format('Y-m-d');
        $this->notes = $issue->notes;
        $this->metadata = $issue->metadata ?? [];
        $this->showModal = true;
    }
    
    public function updatedIssueTypeId()
    {
        // Reset metadata when issue type changes
        $this->metadata = [];
    }
    
    public function save()
    {
        $this->validate([
            'employee_id' => 'required|exists:hcm_employees,id',
            'issue_type_id' => 'required|exists:hcm_employee_issue_types,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'identifier' => 'nullable|string|max:100',
            'issued_at' => 'nullable|date',
            'returned_at' => 'nullable|date|after_or_equal:issued_at',
            'notes' => 'nullable|string',
        ]);
        
        $data = [
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
            'employee_id' => $this->employee_id,
            'issue_type_id' => $this->issue_type_id,
            'title' => $this->title,
            'description' => $this->description,
            'identifier' => $this->identifier,
            'issued_at' => $this->issued_at ?: null,
            'returned_at' => $this->returned_at ?: null,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'status' => $this->returned_at ? 'returned' : ($this->issued_at ? 'issued' : 'pending'),
        ];
        
        if ($this->editingIssue) {
            $this->editingIssue->update($data);
            session()->flash('success', 'Ausgabe erfolgreich aktualisiert!');
        } else {
            HcmEmployeeIssue::create($data);
            session()->flash('success', 'Ausgabe erfolgreich erstellt!');
        }
        
        $this->closeModal();
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function resetForm()
    {
        $this->editingIssue = null;
        $this->employee_id = '';
        $this->issue_type_id = '';
        $this->title = '';
        $this->description = '';
        $this->identifier = '';
        $this->issued_at = '';
        $this->returned_at = '';
        $this->notes = '';
        $this->metadata = [];
    }

    public function render()
    {
        return view('hcm::livewire.issues.index')
            ->layout('platform::layouts.app');
    }
}

