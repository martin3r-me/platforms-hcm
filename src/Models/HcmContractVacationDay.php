<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class HcmContractVacationDay extends Model
{
    protected $table = 'hcm_contract_vacation_days';

    protected $fillable = [
        'uuid',
        'contract_id',
        'employee_id',
        'team_id',
        'vacation_date',
        'type',
        'vacation_hours',
        'vacation_days',
        'status',
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'source',
        'source_reference',
        'notes',
        'metadata',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'vacation_date' => 'date',
        'approved_at' => 'datetime',
        'metadata' => 'array',
        'vacation_hours' => 'decimal:2',
        'vacation_days' => 'decimal:3',
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
