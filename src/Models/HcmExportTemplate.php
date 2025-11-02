<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class HcmExportTemplate extends Model
{
    protected $table = 'hcm_export_templates';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'name',
        'slug',
        'description',
        'configuration',
        'is_active',
        'is_system_template',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'is_system_template' => 'boolean',
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
            
            // Auto-generate slug if not provided
            if (empty($model->slug)) {
                $model->slug = \Illuminate\Support\Str::slug($model->name);
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function exports()
    {
        return $this->hasMany(HcmExport::class, 'export_template_id');
    }
}

