<?php

namespace Platform\Hcm\Livewire\Tariff;

use Livewire\Component;
use Platform\Hcm\Models\HcmTaxClass;

class Index extends Component
{

    public $modalShow = false;

    public $search = '';
    public $sortField = 'code';
    public $sortDirection = 'asc';

    // Form
    public $form = [
        'code' => '',
        'name' => '',
        'is_active' => true,
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openCreateModal(): void
    {
        $this->form = [ 'code' => '', 'name' => '', 'is_active' => true ];
        $this->modalShow = true;
    }

    public function closeCreateModal(): void
    {
        $this->modalShow = false;
    }

    public function save(): void
    {
        $this->validate([
            'form.code' => 'required|string|max:50|unique:hcm_tax_classes,code',
            'form.name' => 'required|string|max:255',
            'form.is_active' => 'boolean',
        ]);

        HcmTaxClass::create([
            'code' => $this->form['code'],
            'name' => $this->form['name'],
            'is_active' => (bool) $this->form['is_active'],
        ]);

        $this->modalShow = false;
        session()->flash('message', 'Tarifklasse gespeichert.');
    }

    public function render()
    {
        $query = HcmTaxClass::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('code', 'like', '%'.$this->search.'%')
                       ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);

        $taxClasses = $query->get();

        return view('hcm::livewire.tariff.index', [
            'taxClasses' => $taxClasses,
        ])->layout('platform::layouts.app');
    }
}


