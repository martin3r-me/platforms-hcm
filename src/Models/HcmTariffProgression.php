<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HcmTariffProgression extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_contract_id',
        'from_tariff_level_id',
        'to_tariff_level_id',
        'progression_date',
        'progression_reason',
        'progression_notes',
    ];

    protected $casts = [
        'progression_date' => 'date',
    ];

    public function employeeContract(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeContract::class, 'employee_contract_id');
    }

    public function fromTariffLevel(): BelongsTo
    {
        return $this->belongsTo(HcmTariffLevel::class, 'from_tariff_level_id');
    }

    public function toTariffLevel(): BelongsTo
    {
        return $this->belongsTo(HcmTariffLevel::class, 'to_tariff_level_id');
    }

    public function getProgressionReasonLabelAttribute(): string
    {
        return match($this->progression_reason) {
            'automatic' => 'Automatisch',
            'manual' => 'Manuell',
            'promotion' => 'Beförderung',
            'adjustment' => 'Anpassung',
            default => 'Unbekannt'
        };
    }
}
