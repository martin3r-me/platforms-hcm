<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

class HcmTariffAgreement extends Model
{
    use LogsActivity;

    protected $table = 'hcm_tariff_agreements';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'is_active',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
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

    public function tariffGroups(): HasMany
    {
        return $this->hasMany(HcmTariffGroup::class, 'tariff_agreement_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(HcmTariffAgreementVersion::class, 'tariff_agreement_id');
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }
}
