<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class HcmInterview extends Model
{
    use SoftDeletes;

    protected $table = 'hcm_interviews';

    protected $fillable = [
        'uuid',
        'interview_type_id',
        'hcm_job_title_id',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'min_participants',
        'max_participants',
        'status',
        'is_active',
        'team_id',
        'reminder_wa_template_id',
        'reminder_hours_before',
        'created_by_user_id',
        'owned_by_user_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'min_participants' => 'integer',
        'max_participants' => 'integer',
        'is_active' => 'boolean',
        'reminder_hours_before' => 'integer',
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

    public function interviewType(): BelongsTo
    {
        return $this->belongsTo(HcmInterviewType::class, 'interview_type_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(HcmJobTitle::class, 'hcm_job_title_id');
    }

    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(
            \Platform\Core\Models\User::class,
            'hcm_interview_user',
            'hcm_interview_id',
            'user_id'
        );
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(HcmInterviewBooking::class, 'hcm_interview_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
