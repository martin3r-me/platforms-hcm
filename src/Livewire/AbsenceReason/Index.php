<?php

namespace Platform\Hcm\Livewire\AbsenceReason;

use Livewire\Component;
use Platform\Hcm\Models\HcmAbsenceReason;

class Index extends Component
{
    public $showCreateModal = false;
    public $showEditModal = false;

    public $editingId = null;
    public $code = '';
    public $name = '';
    public $short_name = '';
    public $description = '';
    public $category = '';
    public $requires_sick_note = false;
    public $is_paid = true;
    public $sort_order = 0;
    public $is_active = true;

    protected $rules = [
        'code' => 'required|string|max:50',
        'name' => 'required|string|max:255',
        'short_name' => 'nullable|string|max:100',
        'description' => 'nullable|string',
        'category' => 'nullable|string|max:50',
        'requires_sick_note' => 'boolean',
        'is_paid' => 'boolean',
        'sort_order' => 'nullable|integer|min:0',
        'is_active' => 'boolean',
    ];

    public function render()
    {
        $items = HcmAbsenceReason::where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('hcm::livewire.absence-reason.index', [
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
        $m = HcmAbsenceReason::findOrFail($id);
        $this->editingId = $m->id;
        $this->code = $m->code;
        $this->name = $m->name;
        $this->short_name = $m->short_name ?? '';
        $this->description = $m->description ?? '';
        $this->category = $m->category ?? '';
        $this->requires_sick_note = (bool) $m->requires_sick_note;
        $this->is_paid = (bool) $m->is_paid;
        $this->sort_order = $m->sort_order ?? 0;
        $this->is_active = (bool) $m->is_active;
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $teamId = auth()->user()->currentTeam->id;
        
        // Unique validation für Code (pro Team)
        $uniqueRule = $this->editingId 
            ? "unique:hcm_absence_reasons,code,{$this->editingId},id,team_id,{$teamId}"
            : "unique:hcm_absence_reasons,code,NULL,id,team_id,{$teamId}";
        
        $this->validate([
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'requires_sick_note' => 'boolean',
            'is_paid' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data = [
            'team_id' => $teamId,
            'code' => $this->code,
            'name' => $this->name,
            'short_name' => $this->short_name ?: null,
            'description' => $this->description ?: null,
            'category' => $this->category ?: null,
            'requires_sick_note' => $this->requires_sick_note,
            'is_paid' => $this->is_paid,
            'sort_order' => $this->sort_order ?? 0,
            'is_active' => $this->is_active,
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->editingId) {
            // Update
            $item = HcmAbsenceReason::findOrFail($this->editingId);
            $item->update($data);
            session()->flash('message', 'Abwesenheitsgrund erfolgreich aktualisiert.');
        } else {
            // Create
            HcmAbsenceReason::create($data);
            session()->flash('message', 'Abwesenheitsgrund erfolgreich erstellt.');
        }

        $this->resetForm();
        $this->showCreateModal = false;
        $this->showEditModal = false;
    }

    public function delete(int $id): void
    {
        $item = HcmAbsenceReason::findOrFail($id);
        
        // Prüfe ob verwendet
        if ($item->absenceDays()->count() > 0) {
            session()->flash('error', 'Abwesenheitsgrund wird noch verwendet und kann nicht gelöscht werden.');
            return;
        }
        
        $item->delete();
        session()->flash('message', 'Abwesenheitsgrund erfolgreich gelöscht.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->short_name = '';
        $this->description = '';
        $this->category = '';
        $this->requires_sick_note = false;
        $this->is_paid = true;
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetValidation();
    }
}
