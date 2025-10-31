<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Traits\Encryptable;
use Symfony\Component\Uid\UuidV7;

class HcmEmployeeBenefit extends Model
{
    use Encryptable;

    protected $table = 'hcm_employee_benefits';

    protected $fillable = [
        'uuid',
        'team_id',
        'employee_id',
        'employee_contract_id',
        'benefit_type',
        'name',
        'description',
        'is_active',
        'start_date',
        'end_date',
        'insurance_company',
        'contract_number',
        'monthly_contribution_employee',
        'monthly_contribution_employer',
        'contribution_frequency',
        'benefit_specific_data',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'benefit_specific_data' => 'array',
    ];

    protected array $encryptable = [
        'monthly_contribution_employee' => 'string',
        'monthly_contribution_employer' => 'string',
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeContract::class, 'employee_contract_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('benefit_type', $type);
    }
}

