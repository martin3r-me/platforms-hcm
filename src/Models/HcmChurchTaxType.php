<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;

class HcmChurchTaxType extends Model
{
    protected $table = 'hcm_church_tax_types';

    protected $fillable = [
        'code', 'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

