<?php

namespace Platform\Hcm\Livewire\TrainingTypes;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployeeTrainingType;

class Index extends Component
{
    public $showCreateModal = false;
    public $showEditModal = false;

    public $editingId = null;
    public $code = '';
    public $name = '';
    public $description = '';
    public $category = '';
    public $requires_certification = false;
    public $validity_months = null;
    public $is_mandatory = false;
    public $is_active = true;

    protected $rules = [
        'code' => 'required|string|max:50',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'nullable|string|max:100',
        'requires_certification' => 'boolean',
        'validity_months' => 'nullable|integer|min:0',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function render()
    {
        return view('hcm::livewire.training-types.index', [
            'items' => $this->trainingTypes,
        ])->layout('platform::layouts.app');
    }

    #[Computed]
    public function trainingTypes()
    {
        return HcmEmployeeTrainingType::where('team_id', auth()->user()->currentTeam->id)
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
        $m = HcmEmployeeTrainingType::findOrFail($id);
        $this->editingId = $m->id;
        $this->code = $m->code;
        $this->name = $m->name;
        $this->description = $m->description;
        $this->category = $m->category;
        $this->requires_certification = $m->requires_certification;
        $this->validity_months = $m->validity_months;
        $this->is_mandatory = $m->is_mandatory;
        $this->is_active = $m->is_active;
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'requires_certification' => $this->requires_certification,
            'validity_months' => $this->validity_months,
            'is_mandatory' => $this->is_mandatory,
            'is_active' => $this->is_active,
            'team_id' => auth()->user()->currentTeam->id,
        ];

        if ($this->editingId) {
            $m = HcmEmployeeTrainingType::findOrFail($this->editingId);
            $m->update($data);
            session()->flash('success', 'Schulungsart erfolgreich aktualisiert!');
        } else {
            HcmEmployeeTrainingType::create($data);
            session()->flash('success', 'Schulungsart erfolgreich erstellt!');
        }

        $this->closeModals();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $m = HcmEmployeeTrainingType::findOrFail($id);
        
        if ($m->trainings()->count() > 0) {
            session()->flash('error', 'Schulungsart kann nicht gelöscht werden, da noch Schulungen zugeordnet sind!');
            return;
        }

        $m->delete();
        session()->flash('success', 'Schulungsart erfolgreich gelöscht!');
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
        $this->description = '';
        $this->category = '';
        $this->requires_certification = false;
        $this->validity_months = null;
        $this->is_mandatory = false;
        $this->is_active = true;
    }
}

