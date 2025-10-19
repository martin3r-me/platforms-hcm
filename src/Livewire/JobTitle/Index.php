<?php

namespace Platform\Hcm\Livewire\JobTitle;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmJobTitle;

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
            'form.code' => 'required|string|max:64|unique:hcm_job_titles,code',
            'form.name' => 'required|string|max:255',
            'form.is_active' => 'boolean',
        ]);

        HcmJobTitle::create([
            'code' => $this->form['code'],
            'name' => $this->form['name'],
            'is_active' => (bool) $this->form['is_active'],
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->modalShow = false;
        session()->flash('message', 'Stellenbezeichnung gespeichert.');
    }

    public function render()
    {
        $titles = HcmJobTitle::query()
            ->where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('code', 'like', '%'.$this->search.'%')
                       ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->paginate(12);

        return view('hcm::livewire.job-title.index', [ 'titles' => $titles ])
            ->layout('platform::layouts.app');
    }
}


