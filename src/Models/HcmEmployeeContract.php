<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'cost_center_id',
        'created_by_user_id',
        'owned_by_user_id',
        'team_id',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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
}


