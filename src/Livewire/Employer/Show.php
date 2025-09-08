<?php

namespace Platform\Hcm\Livewire\Employer;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Models\HcmEmployee;

class Show extends Component
{
    public HcmEmployer $employer;

    // Settings Modal
    public $settingsModalShow = false;
    
    // Settings Form
    public $settingsForm = [
        'employee_number_prefix' => '',
        'employee_number_start' => 1,
        'is_active' => true,
    ];

    public function mount(HcmEmployer $employer)
    {
        $this->employer = $employer->load(['organizationCompanyLinks.company', 'employees.crmContactLinks.contact']);
        
        // Settings Form initialisieren
        $this->settingsForm = [
            'employee_number_prefix' => $this->employer->employee_number_prefix ?? '',
            'employee_number_start' => $this->employer->employee_number_start,
            'is_active' => $this->employer->is_active,
        ];
    }

    public function rules(): array
    {
        return [
            'employer.employer_number' => 'required|string|max:255|unique:hcm_employers,employer_number,' . $this->employer->id,
            'settingsForm.employee_number_prefix' => 'nullable|string|max:10',
            'settingsForm.employee_number_start' => 'required|integer|min:1',
            'settingsForm.is_active' => 'boolean',
        ];
    }

    public function save(): void
    {
        $this->validate();
        $this->employer->save();

        session()->flash('message', 'Arbeitgeber erfolgreich aktualisiert.');
    }

    public function saveSettings(): void
    {
        $this->validate([
            'settingsForm.employee_number_prefix' => 'nullable|string|max:10',
            'settingsForm.employee_number_start' => 'required|integer|min:1',
            'settingsForm.is_active' => 'boolean',
        ]);

        $this->employer->update([
            'employee_number_prefix' => $this->settingsForm['employee_number_prefix'] ?: null,
            'employee_number_start' => $this->settingsForm['employee_number_start'],
            'is_active' => $this->settingsForm['is_active'],
        ]);

        $this->settingsModalShow = false;
        session()->flash('message', 'Einstellungen erfolgreich gespeichert.');
    }

    public function resetEmployeeNumbering(): void
    {
        $this->employer->resetEmployeeNumbering($this->settingsForm['employee_number_start']);
        
        session()->flash('message', 'Mitarbeiter-Nummerierung zurückgesetzt.');
    }

    public function generateTestEmployeeNumber(): string
    {
        return $this->employer->previewNextEmployeeNumber();
    }

    #[Computed]
    public function employees()
    {
        return $this->employer->employees()
            ->with(['crmContactLinks.contact'])
            ->orderBy('employee_number')
            ->get();
    }

    #[Computed]
    public function stats()
    {
        return [
            'total_employees' => $this->employer->employees()->count(),
            'active_employees' => $this->employer->employees()->active()->count(),
            'next_employee_number' => $this->employer->employee_number_next,
            'last_employee_number' => $this->employer->employee_number_next - 1,
        ];
    }

    public function render()
    {
        return view('hcm::livewire.employer.show')
            ->layout('platform::layouts.app');
    }
}
