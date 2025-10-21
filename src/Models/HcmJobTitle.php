<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\Uid\UuidV7;

class HcmJobTitle extends Model
{
    protected $table = 'hcm_job_titles';

    protected $fillable = [
        'uuid','code','name','is_active','created_by_user_id','owned_by_user_id','team_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(
            HcmEmployeeContract::class,
            'hcm_employee_contract_title_links',
            'job_title_id',
            'contract_id'
        );
    }
}


