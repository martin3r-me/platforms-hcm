<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Platform\Organization\Traits\HasCostCenterLinksTrait;
use Platform\Organization\Contracts\CostCenterLinkableInterface;
use Symfony\Component\Uid\UuidV7;

class HcmEmployeeContract extends Model implements CostCenterLinkableInterface
{
    use HasCostCenterLinksTrait;

    protected $table = 'hcm_employee_contracts';

    protected $fillable = [
        'uuid',
        'employee_id',
        'start_date',
        'end_date',
        'contract_type',
        'employment_status',
        'hours_per_month',
        'annual_vacation_days',
        'working_time_model',
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
        'created_by_user_id',
        'owned_by_user_id',
        'team_id',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'tariff_assignment_date' => 'date',
        'tariff_level_start_date' => 'date',
        'next_tariff_level_date' => 'date',
        'is_active' => 'boolean',
    ];

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
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HcmEmployee::class, 'employee_id');
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
}


