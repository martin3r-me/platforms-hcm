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
        'ik_number',
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

    public function employees(): HasMany
    {
        return $this->hasMany(HcmEmployee::class, 'health_insurance_company_id');
    }

    public function getActiveEmployeesCountAttribute(): int
    {
        return $this->employees()->where('is_active', true)->count();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }
}
