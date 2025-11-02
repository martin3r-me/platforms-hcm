<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class HcmExport extends Model
{
    protected $table = 'hcm_exports';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'name',
        'type',
        'format',
        'export_template_id',
        'parameters',
        'file_path',
        'file_name',
        'record_count',
        'file_size',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'record_count' => 'integer',
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function template()
    {
        return $this->belongsTo(HcmExportTemplate::class, 'export_template_id');
    }

    /**
     * Prüft ob der Export abgeschlossen ist
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Prüft ob der Export fehlgeschlagen ist
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Gibt die Datei-URL zurück
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        return \Storage::disk('public')->url($this->file_path);
    }
}

