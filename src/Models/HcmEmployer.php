<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Organization\Contracts\CompanyLinkableInterface;
use Platform\Organization\Traits\HasCompanyLinksTrait as OrgHasCompanyLinksTrait;
use Symfony\Component\Uid\UuidV7;

class HcmEmployer extends Model implements CompanyLinkableInterface
{
    use LogsActivity, OrgHasCompanyLinksTrait;
    
    protected $table = 'hcm_employers';
    
    protected $fillable = [
        'uuid',
        'employer_number',
        'employee_number_prefix', // Optional, nullable
        'employee_number_start', // Start-Nummer für Employee-Nummerierung
        'employee_number_next', // Nächste Employee-Nummer
        'settings', // JSON für HCM-spezifische Settings
        'created_by_user_id',
        'owned_by_user_id',
        'team_id',
        'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'employee_number_start' => 'integer',
        'employee_number_next' => 'integer',
        'settings' => 'array',
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
            
            // Setze start_number wenn nicht gesetzt
            if (empty($model->employee_number_start)) {
                $model->employee_number_start = 1;
            }
            
            // Setze next_number auf start_number wenn nicht gesetzt
            if (empty($model->employee_number_next)) {
                $model->employee_number_next = $model->employee_number_start;
            }
        });
    }
    
    /**
     * Generiert die nächste Employee-Nummer
     */
    public function generateNextEmployeeNumber(): string
    {
        $number = $this->employee_number_next;
        $this->increment('employee_number_next');
        
        $formattedNumber = str_pad($number, 4, '0', STR_PAD_LEFT);
        
        return $this->employee_number_prefix 
            ? $this->employee_number_prefix . $formattedNumber
            : $formattedNumber;
    }

    /**
     * Vorschau der nächsten Employee-Nummer ohne Inkrement.
     */
    public function previewNextEmployeeNumber(): string
    {
        $number = $this->employee_number_next;
        $formattedNumber = str_pad($number, 4, '0', STR_PAD_LEFT);
        
        return $this->employee_number_prefix 
            ? $this->employee_number_prefix . $formattedNumber
            : $formattedNumber;
    }
    
    /**
     * Setzt die Employee-Nummerierung zurück
     */
    public function resetEmployeeNumbering(int $startNumber = 1): void
    {
        $this->update([
            'employee_number_start' => $startNumber,
            'employee_number_next' => $startNumber, // Next = Start
        ]);
    }
    
    /**
     * Beziehungen
     */
    
    public function employees()
    {
        return $this->hasMany(HcmEmployee::class, 'employer_id');
    }
    
    public function createdByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }
    
    public function ownedByUser()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owned_by_user_id');
    }
    
    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }
    
    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
    
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
    
    public function scopeForCompany($query, $companyId)
    {
        return $query->whereHas('organizationCompanyLinks', function($q) use ($companyId) {
            $q->where('organization_entity_id', $companyId);
        });
    }
    
    /**
     * Accessors
     */
    public function getDisplayNameAttribute(): string
    {
        return optional($this->organizations()->first()?->company)->name ?? $this->employer_number;
    }
    
    public function getEmployeeCountAttribute(): int
    {
        return $this->employees()->count();
    }
    
    public function getActiveEmployeeCountAttribute(): int
    {
        return $this->employees()->active()->count();
    }

    // CompanyLinkableInterface
    public function getCompanyLinkableId(): int
    {
        return $this->getKey();
    }

    public function getCompanyLinkableType(): string
    {
        return $this->getMorphClass();
    }

    public function getTeamId(): int
    {
        return (int) $this->team_id;
    }
}
