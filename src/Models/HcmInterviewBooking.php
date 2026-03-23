<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class HcmInterviewBooking extends Model
{
    use SoftDeletes;

    protected $table = 'hcm_interview_bookings';

    protected $fillable = [
        'uuid',
        'hcm_interview_id',
        'hcm_onboarding_id',
        'status',
        'notes',
        'booked_at',
        'is_active',
        'team_id',
        'created_by_user_id',
        'owned_by_user_id',
    ];

    protected $casts = [
        'booked_at' => 'datetime',
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

    public function interview(): BelongsTo
    {
        return $this->belongsTo(HcmInterview::class, 'hcm_interview_id');
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(HcmOnboarding::class, 'hcm_onboarding_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }
}
