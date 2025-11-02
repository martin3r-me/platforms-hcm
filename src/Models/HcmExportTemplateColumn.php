<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class HcmExportTemplateColumn extends Model
{
    protected $table = 'hcm_export_template_columns';

    protected $fillable = [
        'uuid',
        'export_template_id',
        'column_index',
        'header_name',
        'source_field',
        'static_value',
        'transform',
        'sort_order',
    ];

    protected $casts = [
        'column_index' => 'integer',
        'sort_order' => 'integer',
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

    public function template()
    {
        return $this->belongsTo(HcmExportTemplate::class, 'export_template_id');
    }
}

