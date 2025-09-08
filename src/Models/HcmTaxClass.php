<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;

class HcmTaxClass extends Model
{
    protected $table = 'hcm_tax_classes';

    protected $fillable = [
        'code', 'name', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}


