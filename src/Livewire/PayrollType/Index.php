<?php

namespace Platform\Hcm\Livewire\PayrollType;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmPayrollType;

class Index extends Component
{
    use WithPagination;

    public $modalShow = false;
    public $search = '';
    public $form = [
        'code' => '',
        'lanr' => '',
        'name' => '',
        'short_name' => '',
        'typ' => '',
        'category' => '',
        'basis' => '',
        'relevant_gross' => false,
        'relevant_social_sec' => false,
        'relevant_tax' => false,
        'addition_deduction' => 'addition',
        'default_rate' => null,
        'valid_from' => null,
        'valid_to' => null,
        'is_active' => true,
        'display_group' => '',
        'sort_order' => null,
        'description' => '',
    ];

    public function openCreateModal(): void
    {
        $this->form = [
            'code' => '',
            'lanr' => '',
            'name' => '',
            'short_name' => '',
            'typ' => '',
            'category' => '',
            'basis' => '',
            'relevant_gross' => false,
            'relevant_social_sec' => false,
            'relevant_tax' => false,
            'addition_deduction' => 'addition',
            'default_rate' => null,
            'valid_from' => null,
            'valid_to' => null,
            'is_active' => true,
            'display_group' => '',
            'sort_order' => null,
            'description' => '',
        ];
        $this->modalShow = true;
    }

    public function closeCreateModal(): void
    {
        $this->modalShow = false;
    }

    public function save(): void
    {
        $this->validate([
            'form.code' => 'required|string|max:50',
            'form.lanr' => 'nullable|string|max:10',
            'form.name' => 'required|string|max:255',
            'form.short_name' => 'nullable|string|max:100',
            'form.typ' => 'nullable|string|max:50',
            'form.category' => 'nullable|string|max:50',
            'form.basis' => 'nullable|string|max:50',
            'form.relevant_gross' => 'boolean',
            'form.relevant_social_sec' => 'boolean',
            'form.relevant_tax' => 'boolean',
            'form.addition_deduction' => 'required|in:addition,deduction,neutral',
            'form.default_rate' => 'nullable|numeric|min:0',
            'form.valid_from' => 'nullable|date',
            'form.valid_to' => 'nullable|date|after_or_equal:form.valid_from',
            'form.is_active' => 'boolean',
            'form.display_group' => 'nullable|string|max:100',
            'form.sort_order' => 'nullable|integer|min:0',
            'form.description' => 'nullable|string',
        ]);

        HcmPayrollType::create([
            'team_id' => auth()->user()->currentTeam->id,
            'code' => $this->form['code'],
            'lanr' => $this->form['lanr'],
            'name' => $this->form['name'],
            'short_name' => $this->form['short_name'],
            'typ' => $this->form['typ'],
            'category' => $this->form['category'],
            'basis' => $this->form['basis'],
            'relevant_gross' => (bool) $this->form['relevant_gross'],
            'relevant_social_sec' => (bool) $this->form['relevant_social_sec'],
            'relevant_tax' => (bool) $this->form['relevant_tax'],
            'addition_deduction' => $this->form['addition_deduction'],
            'default_rate' => $this->form['default_rate'],
            'valid_from' => $this->form['valid_from'],
            'valid_to' => $this->form['valid_to'],
            'is_active' => (bool) $this->form['is_active'],
            'display_group' => $this->form['display_group'],
            'sort_order' => $this->form['sort_order'],
            'description' => $this->form['description'],
        ]);

        $this->modalShow = false;
        session()->flash('message', 'Lohnart gespeichert.');
    }

    public function render()
    {
        $payrollTypes = HcmPayrollType::query()
            ->with(['debitFinanceAccount', 'creditFinanceAccount'])
            ->where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('code', 'like', '%'.$this->search.'%')
                       ->orWhere('name', 'like', '%'.$this->search.'%')
                       ->orWhere('category', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('hcm::livewire.payroll-type.index', [
            'payrollTypes' => $payrollTypes,
        ])->layout('platform::layouts.app');
    }
}
