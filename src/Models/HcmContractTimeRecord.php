<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class HcmContractTimeRecord extends Model
{
    protected $table = 'hcm_contract_time_records';

    protected $fillable = [
        'uuid',
        'contract_id',
        'employee_id',
        'team_id',
        'record_date',
        'clock_in',
        'clock_out',
        'break_start',
        'break_end',
        'break_minutes',
        'work_minutes',
        'status',
        'is_corrected',
        'corrected_by_user_id',
        'corrected_at',
        'source',
        'source_reference',
        'notes',
        'metadata',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'record_date' => 'date',
        'clock_in' => 'datetime:H:i',
        'clock_out' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'break_minutes' => 'integer',
        'work_minutes' => 'integer',
        'is_corrected' => 'boolean',
        'corrected_at' => 'datetime',
        'metadata' => 'array',
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

    /**
     * Beziehungen
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeContract::class, 'contract_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HcmEmployee::class, 'employee_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }
}
