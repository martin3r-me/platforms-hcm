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

    // SV/Steuer-Zuordnungen
    public $insurance_status_id;
    public $pension_type_id;
    public $employment_relationship_id;
    public $person_group_id;
    public $selected_levy_type_ids = [];
    public $schooling_level;
    public $vocational_training_level;
    public $is_temp_agency = false;
    public $contract_form;
    public $primary_job_activity_id;

    public function mount(HcmEmployeeContract $contract)
    {
        $this->contract = $contract->load([
            'employee',
            'employee.crmContactLinks.contact',
            'tariffGroup',
            'tariffLevel',
            'tariffProgressions.fromTariffLevel',
            'tariffProgressions.toTariffLevel',
            'jobTitles',
            'jobActivities',
            'primaryJobActivity',
            'jobActivityAlias',
            'levyTypes',
            'insuranceStatus',
            'pensionType',
            'employmentRelationship',
            'personGroup',
            'costCenterLinks.costCenter',
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

        $this->insurance_status_id = $this->contract->insurance_status_id;
        $this->pension_type_id = $this->contract->pension_type_id;
        $this->employment_relationship_id = $this->contract->employment_relationship_id;
        $this->person_group_id = $this->contract->person_group_id;
        $this->selected_levy_type_ids = $this->contract->levyTypes->pluck('id')->toArray();
        $this->primary_job_activity_id = $this->contract->primary_job_activity_id;
        $this->schooling_level = $this->contract->schooling_level;
        $this->vocational_training_level = $this->contract->vocational_training_level;
        $this->is_temp_agency = (bool) $this->contract->is_temp_agency;
        $this->contract_form = $this->contract->contract_form;
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
            'insurance_status_id' => 'nullable|exists:hcm_insurance_statuses,id',
            'pension_type_id' => 'nullable|exists:hcm_pension_types,id',
            'employment_relationship_id' => 'nullable|exists:hcm_employment_relationships,id',
            'person_group_id' => 'nullable|exists:hcm_person_groups,id',
            'selected_levy_type_ids' => 'array',
            'selected_levy_type_ids.*' => 'exists:hcm_levy_types,id',
            'primary_job_activity_id' => 'nullable|exists:hcm_job_activities,id',
            'schooling_level' => 'nullable|in:1,2,3,4,9',
            'vocational_training_level' => 'nullable|in:1,2,3,4,5,6,9',
            'is_temp_agency' => 'boolean',
            'contract_form' => 'nullable|in:1,2,3,4,5,6,7,8,9',
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
            'insurance_status_id' => $this->insurance_status_id,
            'pension_type_id' => $this->pension_type_id,
            'employment_relationship_id' => $this->employment_relationship_id,
            'person_group_id' => $this->person_group_id,
            'primary_job_activity_id' => $this->primary_job_activity_id,
            'schooling_level' => $this->schooling_level,
            'vocational_training_level' => $this->vocational_training_level,
            'is_temp_agency' => $this->is_temp_agency,
            'contract_form' => $this->contract_form,
        ]);

        $this->contract->levyTypes()->sync($this->selected_levy_type_ids ?? []);

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
            ->orderByRaw('CAST(code AS UNSIGNED), code')
            ->get();
    }

    public function updatedTariffGroupId()
    {
        $this->tariff_level_id = null;
    }

    public function getInsuranceStatusesProperty()
    {
        return \Platform\Hcm\Models\HcmInsuranceStatus::where('team_id', auth()->user()->current_team_id)->orderBy('code')->get();
    }

    public function getPensionTypesProperty()
    {
        return \Platform\Hcm\Models\HcmPensionType::where('team_id', auth()->user()->current_team_id)->orderBy('code')->get();
    }

    public function getEmploymentRelationshipsProperty()
    {
        return \Platform\Hcm\Models\HcmEmploymentRelationship::where('team_id', auth()->user()->current_team_id)->orderBy('code')->get();
    }

    public function getLevyTypesProperty()
    {
        return \Platform\Hcm\Models\HcmLevyType::where('team_id', auth()->user()->current_team_id)->orderBy('code')->get();
    }

    public function getPersonGroupsProperty()
    {
        return \Platform\Hcm\Models\HcmPersonGroup::where('team_id', auth()->user()->current_team_id)->orderBy('code')->get();
    }

    public function getJobActivitiesProperty()
    {
        return \Platform\Hcm\Models\HcmJobActivity::where('team_id', auth()->user()->current_team_id)
            ->orderBy('code')
            ->limit(500)
            ->get();
    }

    public function getSchoolingLevelOptionsProperty()
    {
        return [
            1 => 'Ohne Schulabschluss',
            2 => 'Haupt-/Volksschule',
            3 => 'Mittlere Reife',
            4 => 'Abitur/Fachabitur',
            9 => 'Unbekannt',
        ];
    }

    public function getVocationalTrainingLevelOptionsProperty()
    {
        return [
            1 => 'Ohne beruflichen Abschluss',
            2 => 'Anerkannte Berufsausbildung',
            3 => 'Meister/Techniker/Fachschule',
            4 => 'Bachelor',
            5 => 'Diplom/Master/Staatsexamen',
            6 => 'Promotion',
            9 => 'Unbekannt',
        ];
    }

    public function getContractFormOptionsProperty()
    {
        return [
            '1' => 'Unbefristet',
            '2' => 'Befristet',
            '3' => 'Ausbildung',
            '4' => 'Praktikum',
            '5' => 'Werkstudent',
            '6' => 'Leiharbeit',
            '7' => 'Minijob',
            '8' => 'Teilzeit',
            '9' => 'Sonstige',
        ];
    }

    public function render()
    {
        return view('hcm::livewire.contract.show')
            ->layout('platform::layouts.app');
    }
}
