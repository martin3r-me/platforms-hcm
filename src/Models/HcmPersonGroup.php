<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;

class HcmPersonGroup extends Model
{
    protected $table = 'hcm_person_groups';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
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
}


