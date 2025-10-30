<?php

namespace Platform\Hcm\Livewire\LevyType;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmLevyType;

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
        $items = HcmLevyType::where('team_id', auth()->user()->current_team_id)
            ->orderBy('code')
            ->paginate(15);

        return view('hcm::livewire.levy-type.index', [
            'items' => $items,
        ])->layout('platform::layouts.app');
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $m = HcmLevyType::findOrFail($id);
        $this->editingId = $m->id;
        $this->code = $m->code;
        $this->name = $m->name;
        $this->description = $m->description;
        $this->is_active = (bool) $m->is_active;
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
            HcmLevyType::where('id', $this->editingId)->update($data);
            session()->flash('success', 'Umlageart aktualisiert.');
        } else {
            HcmLevyType::create($data);
            session()->flash('success', 'Umlageart erstellt.');
        }

        $this->closeModals();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $m = HcmLevyType::findOrFail($id);
        $m->delete();
        session()->flash('success', 'Umlageart gelöscht.');
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


