<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Contracts\InheritsExtraFields;
use Platform\Core\Traits\HasExtraFields;
use Platform\Core\Traits\HasPublicFormLink;
use Platform\Hcm\Traits\HasEmployeeContact;
use Platform\Hcm\Traits\SyncsCrmContactFields;
use Symfony\Component\Uid\UuidV7;

class HcmOnboarding extends Model implements InheritsExtraFields
{
    use HasEmployeeContact;
    use HasExtraFields;
    use HasPublicFormLink;
    use SyncsCrmContactFields;

    protected $table = 'hcm_onboardings';

    protected $fillable = [
        'uuid',
        'progress',
        'enrichment_status',
        'auto_pilot',
        'auto_pilot_completed_at',
        'preferred_comms_channel_id',
        'source_position_title',
        'hcm_job_title_id',
        'notes',
        'is_active',
        'team_id',
        'created_by_user_id',
        'owned_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'progress' => 'integer',
        'auto_pilot' => 'boolean',
        'auto_pilot_completed_at' => 'datetime',
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
    public function crmContactLinks()
    {
        return $this->morphMany(\Platform\Crm\Models\CrmContactLink::class, 'linkable');
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function ownedByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owned_by_user_id');
    }

    public function preferredCommsChannel()
    {
        return $this->belongsTo(\Platform\Crm\Models\CommsChannel::class, 'preferred_comms_channel_id');
    }

    public function jobTitle()
    {
        return $this->belongsTo(HcmJobTitle::class, 'hcm_job_title_id');
    }

    public function interviewBookings()
    {
        return $this->hasMany(HcmInterviewBooking::class, 'hcm_onboarding_id');
    }

    public function getPublicUrl(): string
    {
        return $this->getOrCreatePublicFormLink()->getUrl();
    }

    public function extraFieldParents(): array
    {
        $parents = [];
        if ($this->jobTitle) {
            $parents[] = $this->jobTitle;
        }
        return $parents;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function calculateProgress(): int
    {
        $definitions = $this->getExtraFieldDefinitions();
        $requiredDefinitions = $definitions->where('is_required', true);

        if ($requiredDefinitions->isEmpty()) {
            return 100;
        }

        $values = $this->extraFieldValues()
            ->whereIn('definition_id', $requiredDefinitions->pluck('id'))
            ->get()
            ->keyBy('definition_id');

        $filled = 0;
        foreach ($requiredDefinitions as $def) {
            $val = $values->get($def->id);
            if ($val !== null && $val->value !== null && $val->value !== '') {
                $filled++;
            }
        }

        return (int) round(($filled / $requiredDefinitions->count()) * 100);
    }
}
