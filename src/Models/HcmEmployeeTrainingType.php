<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmEmployeeTrainingType extends Model
{
    protected $table = 'hcm_employee_training_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'requires_certification',
        'validity_months',
        'is_mandatory',
        'team_id',
        'is_active',
    ];

    protected $casts = [
        'requires_certification' => 'boolean',
        'validity_months' => 'integer',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function trainings(): HasMany
    {
        return $this->hasMany(HcmEmployeeTraining::class, 'training_type_id');
    }
}

