<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HcmPayrollType extends Model
{
    use SoftDeletes;
    protected $table = 'hcm_payroll_types';

    protected $fillable = [
        'team_id',
        'code',
        'lanr',
        'name',
        'short_name',
        'typ',
        'category',
        'basis',
        'relevant_gross',
        'relevant_social_sec',
        'relevant_tax',
        'addition_deduction',
        'default_rate',
        'debit_finance_account_id',
        'credit_finance_account_id',
        'valid_from',
        'valid_to',
        'successor_payroll_type_id',
        'is_active',
        'display_group',
        'sort_order',
        'description',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'relevant_gross' => 'boolean',
        'relevant_social_sec' => 'boolean',
        'relevant_tax' => 'boolean',
        'default_rate' => 'decimal:4',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function debitFinanceAccount(): BelongsTo
    {
        return $this->belongsTo(\Platform\Finance\Models\FinanceAccount::class, 'debit_finance_account_id');
    }

    public function creditFinanceAccount(): BelongsTo
    {
        return $this->belongsTo(\Platform\Finance\Models\FinanceAccount::class, 'credit_finance_account_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'successor_payroll_type_id');
    }

    public function predecessors(): HasMany
    {
        return $this->hasMany(self::class, 'successor_payroll_type_id');
    }
}
