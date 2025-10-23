<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class HcmTariffAgreementVersion extends Model
{
    protected $table = 'hcm_tariff_agreement_versions';

    protected $fillable = [
        'uuid',
        'tariff_agreement_id',
        'effective_from',
        'status',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
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

    public function tariffAgreement(): BelongsTo
    {
        return $this->belongsTo(HcmTariffAgreement::class, 'tariff_agreement_id');
    }
}
