<?php

namespace Platform\Hcm\Livewire\PersonGroup;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmPersonGroup;

class Index extends Component
{
    use WithPagination;

    public $showCreateModal = false;
    public $showEditModal = false;

    public $editingId = null;
    public $code = '';
    public $name = '';
    public $description = '';
    public $is_active = true;

    protected $rules = [
        'code' => 'required|string|max:20',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
    ];

    public function render()
    {
        $groups = HcmPersonGroup::where('team_id', auth()->user()->current_team_id)
            ->orderBy('code')
            ->paginate(15);

        return view('hcm::livewire.person-group.index', [
            'groups' => $groups,
        ])->layout('platform::layouts.app');
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $group = HcmPersonGroup::findOrFail($id);
        $this->editingId = $group->id;
        $this->code = $group->code;
        $this->name = $group->name;
        $this->description = $group->description;
        $this->is_active = (bool) $group->is_active;
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'team_id' => auth()->user()->current_team_id,
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->editingId) {
            HcmPersonGroup::where('id', $this->editingId)->update($data);
            session()->flash('success', 'Personengruppenschlüssel aktualisiert.');
        } else {
            HcmPersonGroup::create($data);
            session()->flash('success', 'Personengruppenschlüssel erstellt.');
        }

        $this->closeModals();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $group = HcmPersonGroup::findOrFail($id);
        $group->delete();
        session()->flash('success', 'Personengruppenschlüssel gelöscht.');
    }

    public function closeModals(): void
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->is_active = true;
    }
}


