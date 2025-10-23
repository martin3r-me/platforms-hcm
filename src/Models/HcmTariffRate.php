<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmTariffRate extends Model
{
    protected $table = 'hcm_tariff_rates';

    protected $fillable = [
        'tariff_group_id',
        'tariff_level_id',
        'amount',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function tariffGroup(): BelongsTo
    {
        return $this->belongsTo(HcmTariffGroup::class, 'tariff_group_id');
    }

    public function tariffLevel(): BelongsTo
    {
        return $this->belongsTo(HcmTariffLevel::class, 'tariff_level_id');
    }

    /**
     * Get the current rate for a specific date
     */
    public static function getCurrentRate(int $tariffGroupId, int $tariffLevelId, ?string $date = null): ?self
    {
        $date = $date ?? now()->toDateString();
        
        return self::where('tariff_group_id', $tariffGroupId)
            ->where('tariff_level_id', $tariffLevelId)
            ->where('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                      ->orWhere('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }
}
