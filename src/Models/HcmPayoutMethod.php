<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmPayoutMethod extends Model
{
    protected $table = 'hcm_payout_methods';

    protected $fillable = [
        'team_id',
        'code',
        'name',
        'external_code',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'external_code' => 'integer',
        'is_active' => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(HcmEmployee::class, 'payout_method_id');
    }
}


