<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmHealthInsuranceCompany extends Model
{
    protected $table = 'hcm_health_insurance_companies';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'short_name',
        'description',
        'website',
        'phone',
        'email',
        'address',
        'is_active',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = \Str::uuid();
            }
        });
    }

    // Note: Health insurance company relationship with employees would need to be implemented
    // through employee contracts or a separate pivot table if needed in the future

    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }
}
