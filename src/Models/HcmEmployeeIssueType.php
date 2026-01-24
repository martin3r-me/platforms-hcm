<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class HcmEmployeeIssueType extends Model
{
    protected $table = 'hcm_employee_issue_types';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'code',
        'name',
        'category',
        'requires_return',
        'is_active',
        'field_definitions',
    ];

    protected $casts = [
        'requires_return' => 'boolean',
        'is_active' => 'boolean',
        'field_definitions' => 'array',
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
}


