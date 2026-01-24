<?php

namespace Platform\Hcm\Livewire\IssueTypes;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployeeIssueType;

class Index extends Component
{
    public $search = '';
    public $showCreateModal = false;
    public $showEditModal = false;

    public $editingId = null;
    public $code = '';
    public $name = '';
    public $category = '';
    public $requires_return = true;
    public $is_active = true;
    public $field_definitions = [];

    protected $rules = [
        'code' => 'required|string|max:50|unique:hcm_employee_issue_types,code',
        'name' => 'required|string|max:255',
        'category' => 'nullable|string|max:100',
        'requires_return' => 'boolean',
        'is_active' => 'boolean',
        'field_definitions' => 'nullable|array',
    ];

    public function render()
    {
        return view('hcm::livewire.issue-types.index', [
            'items' => $this->issueTypes,
        ])->layout('platform::layouts.app');
    }

    #[Computed]
    public function issueTypes()
    {
        return HcmEmployeeIssueType::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%')
                        ->orWhere('category', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $m = HcmEmployeeIssueType::findOrFail($id);
        $this->editingId = $m->id;
        $this->code = $m->code;
        $this->name = $m->name;
        $this->category = $m->category;
        $this->requires_return = (bool) $m->requires_return;
        $this->is_active = (bool) $m->is_active;
        $this->field_definitions = $m->field_definitions ?? [];
        $this->showEditModal = true;
    }

    public function save(): void
    {
        if ($this->editingId) {
            $this->rules['code'] = 'required|string|max:50|unique:hcm_employee_issue_types,code,' . $this->editingId;
        }

        $this->validate();

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'requires_return' => $this->requires_return,
            'is_active' => $this->is_active,
            'field_definitions' => !empty($this->field_definitions) ? $this->field_definitions : null,
            'team_id' => auth()->user()->currentTeam->id,
        ];

        if ($this->editingId) {
            $m = HcmEmployeeIssueType::findOrFail($this->editingId);
            $m->update($data);
            session()->flash('success', 'Ausgabe-Typ erfolgreich aktualisiert!');
        } else {
            $data['created_by_user_id'] = auth()->id();
            HcmEmployeeIssueType::create($data);
            session()->flash('success', 'Ausgabe-Typ erfolgreich erstellt!');
        }

        $this->closeModals();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $m = HcmEmployeeIssueType::findOrFail($id);
        
        if ($m->issues()->count() > 0) {
            session()->flash('error', 'Ausgabe-Typ kann nicht gelöscht werden, da noch Ausgaben zugeordnet sind!');
            return;
        }

        $m->delete();
        session()->flash('success', 'Ausgabe-Typ erfolgreich gelöscht!');
    }

    public function addFieldDefinition(): void
    {
        $this->field_definitions[] = [
            'key' => '',
            'type' => 'text',
            'label' => '',
            'placeholder' => '',
            'required' => false,
        ];
    }

    public function removeFieldDefinition(int $index): void
    {
        unset($this->field_definitions[$index]);
        $this->field_definitions = array_values($this->field_definitions);
    }

    public function closeModals(): void
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->editingId = null;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->category = '';
        $this->requires_return = true;
        $this->is_active = true;
        $this->field_definitions = [];
    }
}
