<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Platform\Organization\Traits\HasCostCenterLinksTrait;
use Platform\Organization\Contracts\CostCenterLinkableInterface;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Traits\Encryptable;

class HcmEmployeeContract extends Model implements CostCenterLinkableInterface
{
    use HasCostCenterLinksTrait, Encryptable;

    protected $table = 'hcm_employee_contracts';

    protected $fillable = [
        'uuid',
        'employee_id',
        'start_date',
        'end_date',
        'contract_type',
        'employment_status',
        'hours_per_month',
        'work_days_per_week',
        'annual_vacation_days',
        'working_time_model',
        'insurance_status_id',
        'pension_type_id',
        'employment_relationship_id',
        'primary_job_activity_id',
        'schooling_level',
        'vocational_training_level',
        'is_temp_agency',
        'contract_form',
        'tax_class_id',
        'tax_factor_id',
        'child_allowance',
        'social_security_number',
        'department_id',
        'location_id',
        'cost_center',
        'tariff_group_id',
        'tariff_level_id',
        'tariff_assignment_date',
        'tariff_level_start_date',
        'next_tariff_level_date',
        'is_above_tariff',
        'above_tariff_amount',
        'above_tariff_reason',
        'above_tariff_start_date',
        'is_minimum_wage',
        'minimum_wage_hourly_rate',
        'minimum_wage_monthly_hours',
        'minimum_wage_notes',
        'created_by_user_id',
        'owned_by_user_id',
        'team_id',
        'is_active',
        // Lohn & Urlaub modern
        'wage_base_type',
        'hourly_wage',
        'base_salary',
        'vacation_entitlement',
        'vacation_prev_year',
        'vacation_taken',
        'vacation_expiry_date',
        'vacation_allowance_enabled',
        'vacation_allowance_amount',
        'cost_center_id',
        // Phase 1: Dienstwagen & Fahrtkosten
        'company_car_enabled',
        'travel_cost_reimbursement',
        // Phase 1: Befristung/Probezeit
        'is_fixed_term',
        'fixed_term_end_date',
        'probation_end_date',
        'employment_relationship_type',
        'contract_form',
        // Phase 1: Behinderung Urlaub
        'additional_vacation_disability',
        // Phase 1: Arbeitsort/Standort
        'work_location_name',
        'work_location_address',
        'work_location_postal_code',
        'work_location_city',
        'work_location_state',
        'branch_name',
        // Phase 1: Rentenversicherung
        'pension_insurance_company_number',
        'pension_insurance_exempt',
        // Phase 1: Zusätzliche Beschäftigung
        'is_primary_employment',
        'has_additional_employment',
        // Phase 1: Logis
        'accommodation',
        'attributes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'work_days_per_week' => 'decimal:2',
        'hours_per_month' => 'decimal:2',
        'tariff_assignment_date' => 'date',
        'tariff_level_start_date' => 'date',
        'next_tariff_level_date' => 'date',
        'above_tariff_start_date' => 'date',
        'is_above_tariff' => 'boolean',
        'is_minimum_wage' => 'boolean',
        // folgende Beträge sind verschlüsselt (TEXT) und werden nicht gecastet:
        // above_tariff_amount, minimum_wage_hourly_rate, vacation_allowance_amount, hourly_wage, base_salary
        'minimum_wage_monthly_hours' => 'decimal:2',
        // hourly_wage und base_salary sind verschlüsselt (TEXT), kein Cast
        'vacation_entitlement' => 'decimal:2',
        'vacation_prev_year' => 'decimal:2',
        'vacation_taken' => 'decimal:2',
        'vacation_expiry_date' => 'date',
        'vacation_allowance_enabled' => 'boolean',
        // 'vacation_allowance_amount' verschlüsselt (TEXT)
        'is_active' => 'boolean',
        'is_temp_agency' => 'boolean',
        'company_car_enabled' => 'boolean',
        'is_fixed_term' => 'boolean',
        'fixed_term_end_date' => 'date',
        'probation_end_date' => 'date',
        'additional_vacation_disability' => 'integer',
        'pension_insurance_exempt' => 'boolean',
        'is_primary_employment' => 'boolean',
        'has_additional_employment' => 'boolean',
        'attributes' => 'array',
    ];

    protected array $encryptable = [
        'social_security_number' => 'string',
        'hourly_wage' => 'string',
        'base_salary' => 'string',
        'above_tariff_amount' => 'string',
        'minimum_wage_hourly_rate' => 'string',
        'vacation_allowance_amount' => 'string',
        'travel_cost_reimbursement' => 'string',
    ];

    public function insuranceStatus()
    {
        return $this->belongsTo(HcmInsuranceStatus::class, 'insurance_status_id');
    }

    public function pensionType()
    {
        return $this->belongsTo(HcmPensionType::class, 'pension_type_id');
    }

    public function employmentRelationship()
    {
        return $this->belongsTo(HcmEmploymentRelationship::class, 'employment_relationship_id');
    }

    public function primaryJobActivity()
    {
        return $this->belongsTo(HcmJobActivity::class, 'primary_job_activity_id');
    }

    public function levyTypes(): BelongsToMany
    {
        return $this->belongsToMany(HcmLevyType::class, 'hcm_contract_levy_type', 'contract_id', 'levy_type_id')
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
        
        static::saved(function (self $model) {
            // Set next tariff level date when tariff level is assigned
            if ($model->tariff_level_id && !$model->next_tariff_level_date) {
                $model->setNextTariffLevelDate();
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HcmEmployee::class, 'employee_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(\Platform\Hcm\Models\HcmTaxClass::class, 'tax_class_id');
    }

    public function taxFactor(): BelongsTo
    {
        return $this->belongsTo(\Platform\Hcm\Models\HcmTaxFactor::class, 'tax_factor_id');
    }

    public function jobTitles(): BelongsToMany
    {
        return $this->belongsToMany(
            HcmJobTitle::class,
            'hcm_employee_contract_title_links',
            'contract_id',
            'job_title_id'
        );
    }

    public function jobActivities(): BelongsToMany
    {
        return $this->belongsToMany(
            HcmJobActivity::class,
            'hcm_employee_contract_activity_links',
            'contract_id',
            'job_activity_id'
        );
    }

    // CostCenterLinkableInterface
    public function getCostCenterLinkableId(): int
    {
        return $this->id;
    }

    public function getCostCenterLinkableType(): string
    {
        return static::class;
    }

    public function getTeamId(): int
    {
        return $this->team_id;
    }

    public function tariffGroup(): BelongsTo
    {
        return $this->belongsTo(HcmTariffGroup::class, 'tariff_group_id');
    }

    public function tariffLevel(): BelongsTo
    {
        return $this->belongsTo(HcmTariffLevel::class, 'tariff_level_id');
    }

    public function issues()
    {
        return $this->hasMany(HcmEmployeeIssue::class, 'contract_id');
    }

    public function tariffProgressions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HcmTariffProgression::class, 'employee_contract_id');
    }

    public function compensationEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HcmContractCompensationEvent::class, 'employee_contract_id');
    }

    public function vacationEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HcmContractVacationEvent::class, 'employee_contract_id');
    }

    public function benefits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HcmEmployeeBenefit::class, 'employee_contract_id');
    }

    /**
     * Get current tariff rate for this contract
     */
    public function getCurrentTariffRate(?string $date = null): ?HcmTariffRate
    {
        if (!$this->tariff_group_id || !$this->tariff_level_id) {
            return null;
        }

        return HcmTariffRate::getCurrentRate(
            $this->tariff_group_id,
            $this->tariff_level_id,
            $date
        );
    }

    /**
     * Check if tariff level progression is due
     */
    public function isTariffProgressionDue(?string $date = null): bool
    {
        if (!$this->next_tariff_level_date) {
            return false;
        }

        $date = $date ?? now()->toDateString();
        return $this->next_tariff_level_date <= $date;
    }

    /**
     * Set next tariff level date based on current tariff level
     */
    public function setNextTariffLevelDate(): void
    {
        if (!$this->tariffLevel || $this->tariffLevel->isFinalLevel()) {
            $this->next_tariff_level_date = null;
            $this->save();
            return;
        }

        $startDate = $this->tariff_level_start_date ?? $this->start_date;
        $progressionMonths = $this->tariffLevel->progression_months;
        
        if ($progressionMonths === 999) {
            // Endstufe - keine weitere Progression
            $this->next_tariff_level_date = null;
        } else {
            // Berechne nächstes Progression-Datum
            $this->next_tariff_level_date = \Carbon\Carbon::parse($startDate)
                ->addMonths($progressionMonths)
                ->toDateString();
        }
        
        $this->save();
    }

    /**
     * Update next tariff level date for all contracts with tariff assignments
     */
    public static function updateAllNextTariffLevelDates(): int
    {
        $contracts = self::whereNotNull('tariff_level_id')
            ->where('is_active', true)
            ->get();
            
        $updated = 0;
        foreach ($contracts as $contract) {
            $contract->setNextTariffLevelDate();
            $updated++;
        }
        
        return $updated;
    }

    /**
     * Get effective monthly salary (tariff + above tariff + minimum wage)
     */
    public function getEffectiveMonthlySalary(?string $date = null): float
    {
        $baseAmount = 0;
        
        // Tariflicher Anteil
        if ($this->tariff_group_id && $this->tariff_level_id) {
            $tariffRate = $this->getCurrentTariffRate($date);
            if ($tariffRate) {
                $baseAmount = $tariffRate->amount;
            }
        }
        
        // Übertariflicher Anteil
        if ($this->is_above_tariff && $this->above_tariff_amount) {
            $baseAmount += $this->above_tariff_amount;
        }
        
        // Mindestlohn (stundenbasiert)
        if ($this->is_minimum_wage && $this->minimum_wage_hourly_rate && $this->minimum_wage_monthly_hours) {
            $baseAmount = max($baseAmount, $this->minimum_wage_hourly_rate * $this->minimum_wage_monthly_hours);
        }
        
        return $baseAmount;
    }

    /**
     * Get salary type description
     */
    public function getSalaryTypeDescription(): string
    {
        if ($this->is_minimum_wage) {
            return 'Mindestlohn (stundenbasiert)';
        }
        
        if ($this->is_above_tariff) {
            return 'Übertariflich';
        }
        
        if ($this->tariff_group_id && $this->tariff_level_id) {
            return 'Tariflich';
        }
        
        return 'Nicht zugeordnet';
    }
}


