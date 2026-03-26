<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Traits\HasExtraFields;
use Symfony\Component\Uid\UuidV7;

class HcmContractTemplate extends Model
{
    use SoftDeletes;
    use HasExtraFields;

    protected $table = 'hcm_contract_templates';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'description',
        'content',
        'requires_signature',
        'is_active',
        'sort_order',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'requires_signature' => 'boolean',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function onboardingContracts(): HasMany
    {
        return $this->hasMany(HcmOnboardingContract::class, 'hcm_contract_template_id');
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
