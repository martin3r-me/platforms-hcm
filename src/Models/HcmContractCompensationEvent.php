<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Traits\Encryptable;

class HcmContractCompensationEvent extends Model
{
    use Encryptable;
    
    protected $table = 'hcm_contract_compensation_events';

    protected $fillable = [
        'team_id',
        'employee_id',
        'employee_contract_id',
        'effective_date',
        'wage_base_type',
        'hourly_wage',
        'base_salary',
        'reason',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
        // hourly_wage und base_salary sind verschlüsselt (TEXT), kein Cast
    ];

    protected array $encryptable = [
        'hourly_wage' => 'string',
        'base_salary' => 'string',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HcmEmployee::class, 'employee_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeContract::class, 'employee_contract_id');
    }
}


