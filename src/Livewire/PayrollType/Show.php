<?php

namespace Platform\Hcm\Livewire\PayrollType;

use Livewire\Component;
use Platform\Hcm\Models\HcmPayrollType;
use Platform\Hcm\Services\FinanceAccountService;

class Show extends Component
{
    public HcmPayrollType $payrollType;
    public array $form = [];
    public array $financeAccounts = [];

    public function mount(HcmPayrollType $payrollType): void
    {
        $teamId = auth()->user()->currentTeam->id ?? null;
        abort_unless($teamId && $payrollType->team_id === $teamId, 403);

        $this->payrollType = $payrollType;
        $this->financeAccounts = FinanceAccountService::getAccountsForTeam($teamId);
        
        $this->form = [
            'code' => $payrollType->code,
            'lanr' => $payrollType->lanr,
            'name' => $payrollType->name,
            'short_name' => $payrollType->short_name,
            'typ' => $payrollType->typ,
            'category' => $payrollType->category,
            'basis' => $payrollType->basis,
            'relevant_gross' => (bool) $payrollType->relevant_gross,
            'relevant_social_sec' => (bool) $payrollType->relevant_social_sec,
            'relevant_tax' => (bool) $payrollType->relevant_tax,
            'addition_deduction' => $payrollType->addition_deduction,
            'default_rate' => $payrollType->default_rate,
            'valid_from' => optional($payrollType->valid_from)->format('Y-m-d'),
            'valid_to' => optional($payrollType->valid_to)->format('Y-m-d'),
            'is_active' => (bool) $payrollType->is_active,
            'display_group' => $payrollType->display_group,
            'description' => $payrollType->description,
            'debit_finance_account_id' => $payrollType->debit_finance_account_id,
            'credit_finance_account_id' => $payrollType->credit_finance_account_id,
        ];
    }

    protected function rules(): array
    {
        return [
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
            'form.description' => 'nullable|string',
            'form.debit_finance_account_id' => 'nullable|exists:finance_accounts,id',
            'form.credit_finance_account_id' => 'nullable|exists:finance_accounts,id',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $this->payrollType->update([
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
            'description' => $this->form['description'],
            'debit_finance_account_id' => $this->form['debit_finance_account_id'] ?: null,
            'credit_finance_account_id' => $this->form['credit_finance_account_id'] ?: null,
        ]);

        session()->flash('message', 'Lohnart aktualisiert.');
    }

    public function render()
    {
        return view('hcm::livewire.payroll-type.show', [
            'payrollType' => $this->payrollType->fresh(),
        ])->layout('platform::layouts.app');
    }
}

