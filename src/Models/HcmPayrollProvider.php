<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;

class HcmPayrollProvider extends Model
{
    protected $table = 'hcm_payroll_providers';

    protected $fillable = [
        'key',
        'name',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}


