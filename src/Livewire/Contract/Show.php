<?php

namespace Platform\Hcm\Livewire\Contract;

use Livewire\Component;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Services\TariffProgressionService;

class Show extends Component
{
    public HcmEmployeeContract $contract;
    public $editMode = false;
    public $showProgressionModal = false;
    public $showAboveTariffModal = false;
    public $showMinimumWageModal = false;

    // Form fields
    public $tariff_group_id;
    public $tariff_level_id;
    public $is_above_tariff = false;
    public $above_tariff_amount;
    public $above_tariff_reason;
    public $above_tariff_start_date;
    public $is_minimum_wage = false;
    public $minimum_wage_hourly_rate;
    public $minimum_wage_monthly_hours;
    public $minimum_wage_notes;

    public function mount(HcmEmployeeContract $contract)
    {
        $this->contract = $contract->load([
            'employee',
            'tariffGroup',
            'tariffLevel',
            'tariffProgressions.fromTariffLevel',
            'tariffProgressions.toTariffLevel',
            'jobTitles',
            'jobActivities'
        ]);
        
        $this->loadFormData();
    }

    public function loadFormData()
    {
        $this->tariff_group_id = $this->contract->tariff_group_id;
        $this->tariff_level_id = $this->contract->tariff_level_id;
        $this->is_above_tariff = $this->contract->is_above_tariff;
        $this->above_tariff_amount = $this->contract->above_tariff_amount;
        $this->above_tariff_reason = $this->contract->above_tariff_reason;
        $this->above_tariff_start_date = $this->contract->above_tariff_start_date?->format('Y-m-d');
        $this->is_minimum_wage = $this->contract->is_minimum_wage;
        $this->minimum_wage_hourly_rate = $this->contract->minimum_wage_hourly_rate;
        $this->minimum_wage_monthly_hours = $this->contract->minimum_wage_monthly_hours;
        $this->minimum_wage_notes = $this->contract->minimum_wage_notes;
    }

    public function toggleEdit()
    {
        $this->editMode = !$this->editMode;
        if (!$this->editMode) {
            $this->loadFormData();
        }
    }

    public function save()
    {
        $this->validate([
            'tariff_group_id' => 'nullable|exists:hcm_tariff_groups,id',
            'tariff_level_id' => 'nullable|exists:hcm_tariff_levels,id',
            'is_above_tariff' => 'boolean',
            'above_tariff_amount' => 'nullable|numeric|min:0',
            'above_tariff_reason' => 'nullable|string|max:500',
            'above_tariff_start_date' => 'nullable|date',
            'is_minimum_wage' => 'boolean',
            'minimum_wage_hourly_rate' => 'nullable|numeric|min:0',
            'minimum_wage_monthly_hours' => 'nullable|numeric|min:0',
            'minimum_wage_notes' => 'nullable|string|max:500',
        ]);

        $this->contract->update([
            'tariff_group_id' => $this->tariff_group_id,
            'tariff_level_id' => $this->tariff_level_id,
            'is_above_tariff' => $this->is_above_tariff,
            'above_tariff_amount' => $this->above_tariff_amount,
            'above_tariff_reason' => $this->above_tariff_reason,
            'above_tariff_start_date' => $this->above_tariff_start_date,
            'is_minimum_wage' => $this->is_minimum_wage,
            'minimum_wage_hourly_rate' => $this->minimum_wage_hourly_rate,
            'minimum_wage_monthly_hours' => $this->minimum_wage_monthly_hours,
            'minimum_wage_notes' => $this->minimum_wage_notes,
        ]);

        $this->editMode = false;
        $this->contract->refresh();
        $this->loadFormData();
        
        session()->flash('success', 'Vertrag erfolgreich aktualisiert!');
    }

    public function getTariffGroupsProperty()
    {
        return \Platform\Hcm\Models\HcmTariffGroup::orderBy('name')->get();
    }

    public function getTariffLevelsProperty()
    {
        if (!$this->tariff_group_id) {
            return collect();
        }
        return \Platform\Hcm\Models\HcmTariffLevel::where('tariff_group_id', $this->tariff_group_id)
            ->orderBy('code')
            ->get();
    }

    public function updatedTariffGroupId()
    {
        $this->tariff_level_id = null;
    }

    public function render()
    {
        return view('hcm::livewire.contract.show');
    }
}
