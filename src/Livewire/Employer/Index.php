<?php

namespace Platform\Hcm\Livewire\Employer;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Organization\Models\OrganizationEntity;

class Index extends Component
{
    // Modal State
    public $modalShow = false;
    
    // Sorting
    public $sortField = 'employer_number';
    public $sortDirection = 'asc';
    
    // Form Data
    public $employer_number = '';
    public $organization_entity_id = '';
    public $employee_number_prefix = '';
    public $employee_number_start = 1;
    public $is_active = true;

    protected $rules = [
        'employer_number' => 'required|string|max:255|unique:hcm_employers,employer_number',
        'organization_entity_id' => 'required|exists:organization_entities,id',
        'employee_number_prefix' => 'nullable|string|max:10',
        'employee_number_start' => 'required|integer|min:1',
        'is_active' => 'boolean',
    ];

    #[Computed]
    public function employers()
    {
        $query = HcmEmployer::with(['organizationCompanyLinks.company', 'employees'])
            ->forTeam(auth()->user()->currentTeam->id);

        if ($this->sortField === 'employer_number') {
            $query->orderBy('employer_number', $this->sortDirection);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return $query->get();
    }

    #[Computed]
    public function availableCompanies()
    {
        // Aktive Organization-Entities aus dem Team, die noch nicht mit einem Arbeitgeber verknüpft sind
        $linkedIds = \Platform\Organization\Models\OrganizationCompanyLink::where('linkable_type', 'Platform\\Hcm\\Models\\HcmEmployer')
            ->where('team_id', auth()->user()->currentTeam->id)
            ->pluck('organization_entity_id');

        return OrganizationEntity::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->whereNotIn('id', $linkedIds)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;
        
        return [
            'total' => HcmEmployer::forTeam($teamId)->count(),
            'active' => HcmEmployer::forTeam($teamId)->active()->count(),
            'inactive' => HcmEmployer::forTeam($teamId)->where('is_active', false)->count(),
            'with_employees' => HcmEmployer::forTeam($teamId)->whereHas('employees')->count(),
        ];
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        return view('hcm::livewire.employer.index')
            ->layout('platform::layouts.app');
    }

    public function createEmployer()
    {
        $this->validate();
        
        $employer = HcmEmployer::create([
            'employer_number' => $this->employer_number,
            'employee_number_prefix' => $this->employee_number_prefix ?: null,
            'employee_number_start' => $this->employee_number_start,
            'employee_number_next' => $this->employee_number_start,
            'is_active' => $this->is_active,
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
        ]);

        // Verknüpfe mit Organization Entity
        $entity = OrganizationEntity::find($this->organization_entity_id);
        if ($entity) {
            $employer->attachOrganization($entity);
        }

        $this->resetForm();
        $this->modalShow = false;
        
        session()->flash('message', 'Arbeitgeber erfolgreich erstellt!');
    }

    public function resetForm()
    {
        $this->reset([
            'employer_number', 
            'organization_entity_id', 
            'employee_number_prefix', 
            'employee_number_start', 
            'is_active'
        ]);
        $this->employee_number_start = 1;
        $this->is_active = true;
    }

    public function openCreateModal()
    {
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->resetForm();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
}
