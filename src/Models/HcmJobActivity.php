<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class HcmJobActivity extends Model
{
    protected $table = 'hcm_job_activities';

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

    public function aliases()
    {
        return $this->hasMany(HcmJobActivityAlias::class, 'job_activity_id');
    }
}


