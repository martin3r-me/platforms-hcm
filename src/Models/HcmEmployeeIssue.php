<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class HcmEmployeeIssue extends Model
{
    protected $table = 'hcm_employee_issues';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'employee_id',
        'contract_id',
        'issue_type_id',
        'title',
        'description',
        'identifier',
        'status',
        'issued_at',
        'returned_at',
        'metadata',
        'notes',
        'signature_data',
        'signed_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'returned_at' => 'date',
        'metadata' => 'array',
        'signed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HcmEmployee::class, 'employee_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeContract::class, 'contract_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeIssueType::class, 'issue_type_id');
    }

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
}


