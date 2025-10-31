<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class HcmEmployeeTraining extends Model
{
    protected $table = 'hcm_employee_trainings';

    protected $fillable = [
        'employee_id',
        'contract_id',
        'training_type_id',
        'title',
        'provider',
        'completed_date',
        'valid_from',
        'valid_until',
        'status',
        'notes',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'completed_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HcmEmployee::class, 'employee_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeContract::class, 'contract_id');
    }

    public function trainingType(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeTrainingType::class, 'training_type_id');
    }

    /**
     * Prüft ob die Schulung abgelaufen ist
     */
    public function isExpired(): bool
    {
        if (!$this->valid_until) {
            return false;
        }
        return $this->valid_until->isPast();
    }

    /**
     * Berechnet Gültigkeitsende basierend auf validity_months
     */
    public function calculateValidUntil(?Carbon $from = null): ?Carbon
    {
        if (!$this->trainingType || !$this->trainingType->validity_months) {
            return null;
        }

        $from = $from ?? $this->completed_date ?? $this->valid_from;
        if (!$from) {
            return null;
        }

        return Carbon::parse($from)->addMonths($this->trainingType->validity_months);
    }
}

