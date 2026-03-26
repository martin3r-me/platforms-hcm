<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Contracts\InheritsExtraFields;
use Platform\Core\Traits\HasExtraFields;
use Symfony\Component\Uid\UuidV7;

class HcmOnboardingContract extends Model implements InheritsExtraFields
{
    use HasExtraFields;

    protected $table = 'hcm_onboarding_contracts';

    protected $fillable = [
        'uuid',
        'hcm_onboarding_id',
        'hcm_contract_template_id',
        'team_id',
        'status',
        'personalized_content',
        'signature_data',
        'signed_at',
        'sent_at',
        'completed_at',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'sent_at' => 'datetime',
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

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(HcmOnboarding::class, 'hcm_onboarding_id');
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(HcmContractTemplate::class, 'hcm_contract_template_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function extraFieldParents(): array
    {
        $parents = [];
        if ($this->contractTemplate) {
            $parents[] = $this->contractTemplate;
        }
        return $parents;
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
