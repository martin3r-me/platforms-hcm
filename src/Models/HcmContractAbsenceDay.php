<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class HcmContractAbsenceDay extends Model
{
    protected $table = 'hcm_contract_absence_days';

    protected $fillable = [
        'uuid',
        'contract_id',
        'employee_id',
        'team_id',
        'absence_date',
        'type',
        'absence_hours',
        'absence_days',
        'absence_reason_id',
        'reason_custom',
        'has_sick_note',
        'sick_note_from',
        'sick_note_until',
        'sick_note_number',
        'status',
        'confirmed_by_user_id',
        'confirmed_at',
        'rejection_reason',
        'source',
        'source_reference',
        'source_synced_at',
        'notes',
        'metadata',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'absence_date' => 'date',
        'has_sick_note' => 'boolean',
        'sick_note_from' => 'date',
        'sick_note_until' => 'date',
        'confirmed_at' => 'datetime',
        'source_synced_at' => 'datetime',
        'metadata' => 'array',
        'absence_hours' => 'decimal:2',
        'absence_days' => 'decimal:3',
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

    public function absenceReason(): BelongsTo
    {
        return $this->belongsTo(HcmAbsenceReason::class, 'absence_reason_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }
}
