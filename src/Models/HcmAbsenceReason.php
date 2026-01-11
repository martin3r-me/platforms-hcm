<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class HcmAbsenceReason extends Model
{
    protected $table = 'hcm_absence_reasons';

    protected $fillable = [
        'uuid',
        'team_id',
        'code',
        'name',
        'short_name',
        'description',
        'category',
        'requires_sick_note',
        'is_paid',
        'sort_order',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'requires_sick_note' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
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

    /**
     * Beziehung zu Abwesenheitstagen
     */
    public function absenceDays(): HasMany
    {
        return $this->hasMany(HcmContractAbsenceDay::class, 'absence_reason_id');
    }
}
