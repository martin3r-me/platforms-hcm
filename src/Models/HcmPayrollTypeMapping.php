<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmPayrollTypeMapping extends Model
{
    protected $table = 'hcm_payroll_type_mappings';

    protected $fillable = [
        'team_id',
        'payroll_type_id',
        'provider_id',
        'external_code',
        'external_label',
        'valid_from',
        'valid_to',
        'meta',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'meta' => 'array',
    ];

    public function payrollType(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollType::class, 'payroll_type_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollProvider::class, 'provider_id');
    }
}


