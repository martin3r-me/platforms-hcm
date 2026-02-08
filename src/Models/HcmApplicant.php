<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Traits\HasExtraFields;
use Platform\Hcm\Traits\HasEmployeeContact;
use Symfony\Component\Uid\UuidV7;

class HcmApplicant extends Model
{
    use HasEmployeeContact;
    use HasExtraFields;

    protected $table = 'hcm_applicants';

    protected $fillable = [
        'uuid',
        'applicant_status_id',
        'progress',
        'notes',
        'applied_at',
        'is_active',
        'auto_pilot',
        'auto_pilot_completed_at',
        'auto_pilot_state_id',
        'team_id',
        'created_by_user_id',
        'owned_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_pilot' => 'boolean',
        'auto_pilot_completed_at' => 'datetime',
        'auto_pilot_state_id' => 'integer',
        'progress' => 'integer',
        'applied_at' => 'date',
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

    public function applicantStatus()
    {
        return $this->belongsTo(HcmApplicantStatus::class, 'applicant_status_id');
    }

    public function autoPilotState()
    {
        return $this->belongsTo(HcmAutoPilotState::class, 'auto_pilot_state_id');
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
