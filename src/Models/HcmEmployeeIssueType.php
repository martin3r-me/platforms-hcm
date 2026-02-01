<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'requires_signature',
    ];

    protected $casts = [
        'requires_return' => 'boolean',
        'is_active' => 'boolean',
        'field_definitions' => 'array',
        'requires_signature' => 'boolean',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(HcmEmployeeIssue::class, 'issue_type_id');
    }

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


