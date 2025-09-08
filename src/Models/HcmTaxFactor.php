<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;

class HcmTaxFactor extends Model
{
    protected $table = 'hcm_tax_factors';

    protected $fillable = [
        'code', 'name', 'value', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}


