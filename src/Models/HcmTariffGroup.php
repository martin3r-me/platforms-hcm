<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmTariffGroup extends Model
{
    protected $table = 'hcm_tariff_groups';

    protected $fillable = [
        'tariff_agreement_id',
        'code',
        'name',
    ];

    public function tariffAgreement(): BelongsTo
    {
        return $this->belongsTo(HcmTariffAgreement::class, 'tariff_agreement_id');
    }

    public function tariffLevels(): HasMany
    {
        return $this->hasMany(HcmTariffLevel::class, 'tariff_group_id');
    }

    public function tariffRates(): HasMany
    {
        return $this->hasMany(HcmTariffRate::class, 'tariff_group_id');
    }
}
