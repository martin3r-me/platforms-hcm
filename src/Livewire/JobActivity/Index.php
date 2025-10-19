<?php

namespace Platform\Hcm\Livewire\JobActivity;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmJobActivity;

class Index extends Component
{
    use WithPagination;

    public $modalShow = false;
    public $search = '';
    public $form = [ 'code' => '', 'name' => '', 'is_active' => true ];

    public function openCreateModal(): void { $this->modalShow = true; }
    public function closeCreateModal(): void { $this->modalShow = false; }

    public function save(): void
    {
        $this->validate([
            'form.code' => 'required|string|max:64|unique:hcm_job_activities,code',
            'form.name' => 'required|string|max:255',
            'form.is_active' => 'boolean',
        ]);

        HcmJobActivity::create([
            'code' => $this->form['code'],
            'name' => $this->form['name'],
            'is_active' => (bool) $this->form['is_active'],
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->modalShow = false;
        session()->flash('message', 'Tätigkeit gespeichert.');
    }

    public function render()
    {
        $activities = HcmJobActivity::query()
            ->where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('code', 'like', '%'.$this->search.'%')
                       ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('hcm::livewire.job-activity.index', [ 'activities' => $activities ])
            ->layout('platform::layouts.app');
    }
}


