<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmAutoPilotLog extends Model
{
    public $timestamps = false;

    protected $table = 'hcm_auto_pilot_logs';

    protected $fillable = [
        'hcm_applicant_id',
        'type',
        'summary',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(HcmApplicant::class, 'hcm_applicant_id');
    }
}
