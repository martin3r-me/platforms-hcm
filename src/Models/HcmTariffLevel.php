<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmTariffLevel extends Model
{
    protected $table = 'hcm_tariff_levels';

    protected $fillable = [
        'tariff_group_id',
        'code',
        'name',
        'progression_months',
    ];

    protected $casts = [
        'progression_months' => 'integer',
    ];

    public function tariffGroup(): BelongsTo
    {
        return $this->belongsTo(HcmTariffGroup::class, 'tariff_group_id');
    }

    public function tariffRates(): HasMany
    {
        return $this->hasMany(HcmTariffRate::class, 'tariff_level_id');
    }

    /**
     * Get the next tariff level in progression
     */
    public function getNextLevel(): ?HcmTariffLevel
    {
        return $this->tariffGroup
            ->tariffLevels()
            ->where('code', '>', $this->code)
            ->orderBy('code')
            ->first();
    }

    /**
     * Check if this is the final level (no progression)
     */
    public function isFinalLevel(): bool
    {
        return $this->progression_months === 999 || $this->getNextLevel() === null;
    }
}
