<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmContractVacationEvent extends Model
{
    protected $table = 'hcm_contract_vacation_events';

    protected $fillable = [
        'team_id',
        'employee_id',
        'employee_contract_id',
        'effective_date',
        'vacation_entitlement',
        'vacation_prev_year',
        'vacation_taken',
        'reason',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'vacation_entitlement' => 'decimal:2',
        'vacation_prev_year' => 'decimal:2',
        'vacation_taken' => 'decimal:2',
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


