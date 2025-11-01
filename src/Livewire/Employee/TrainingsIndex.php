<?php

namespace Platform\Hcm\Livewire\Employee;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeTraining;

class TrainingsIndex extends Component
{
    public HcmEmployee $employee;

    public function mount(HcmEmployee $employee)
    {
        $this->employee = $employee;
    }

    #[Computed]
    public function trainings()
    {
        return HcmEmployeeTraining::where('employee_id', $this->employee->id)
            ->with(['contract', 'trainingType'])
            ->orderBy('completed_date', 'desc')
            ->get();
    }

    public function render()
    {
        return view('hcm::livewire.employee.trainings-index')
            ->layout('platform::layouts.app');
    }
}

