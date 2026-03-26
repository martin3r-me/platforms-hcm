<?php

namespace Platform\Hcm\Livewire\ContractTemplates;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmContractTemplate;

class Index extends Component
{
    public $showCreateModal = false;
    public $showEditModal = false;

    public $editingId = null;
    public $name = '';
    public $code = '';
    public $description = '';
    public $content = '';
    public $requires_signature = true;
    public $sort_order = 0;
    public $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:20',
        'description' => 'nullable|string',
        'content' => 'nullable|string',
        'requires_signature' => 'boolean',
        'sort_order' => 'integer|min:0',
        'is_active' => 'boolean',
    ];

    public function render()
    {
        return view('hcm::livewire.contract-templates.index', [
            'items' => $this->contractTemplates,
        ])->layout('platform::layouts.app');
    }

    #[Computed]
    public function contractTemplates()
    {
        return HcmContractTemplate::where('team_id', auth()->user()->currentTeam->id)
            ->withCount('onboardingContracts')
            ->orderBy('sort_order')
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
        $m = HcmContractTemplate::findOrFail($id);
        $this->editingId = $m->id;
        $this->name = $m->name;
        $this->code = $m->code;
        $this->description = $m->description;
        $this->content = $m->content;
        $this->requires_signature = $m->requires_signature;
        $this->sort_order = $m->sort_order;
        $this->is_active = $m->is_active;
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'content' => $this->content,
            'requires_signature' => $this->requires_signature,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'team_id' => auth()->user()->currentTeam->id,
        ];

        if ($this->editingId) {
            $m = HcmContractTemplate::findOrFail($this->editingId);
            $m->update($data);
            session()->flash('success', 'Vertragsvorlage erfolgreich aktualisiert!');
        } else {
            $data['created_by_user_id'] = auth()->id();
            HcmContractTemplate::create($data);
            session()->flash('success', 'Vertragsvorlage erfolgreich erstellt!');
        }

        $this->closeModals();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $m = HcmContractTemplate::findOrFail($id);

        if ($m->onboardingContracts()->count() > 0) {
            session()->flash('error', 'Vertragsvorlage kann nicht gelöscht werden, da noch Verträge zugeordnet sind!');
            return;
        }

        $m->delete();
        session()->flash('success', 'Vertragsvorlage erfolgreich gelöscht!');
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
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->content = '';
        $this->requires_signature = true;
        $this->sort_order = 0;
        $this->is_active = true;
    }
}
