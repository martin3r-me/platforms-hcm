<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Hcm\Contracts\EmployeeInterface;
use Platform\Hcm\Traits\HasEmployeeContact;
use Platform\Crm\Contracts\CompanyInterface;
use Platform\Crm\Contracts\ContactInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class HcmEmployee extends Model implements EmployeeInterface
{
    use LogsActivity, HasEmployeeContact;
    
    protected $table = 'hcm_employees';
    
    protected $fillable = [
        'uuid',
        'employee_number',
        'employer_id', // FK zu HcmEmployer
        'company_employee_number', // Unternehmensspezifische Personalnummer
        'health_insurance_company_id', // FK zu HcmHealthInsuranceCompany
        'created_by_user_id',
        'owned_by_user_id',
        'team_id',
        'is_active'
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
    
    /**
     * Interface-Implementierung
     */
    public function getEmployeeId(): int
    {
        return $this->id;
    }
    
    public function getEmployeeNumber(): string
    {
        return $this->employee_number;
    }
    
    public function getTeamId(): int
    {
        return $this->team_id;
    }
    
    public function isActive(): bool
    {
        return $this->is_active;
    }
    
    /**
     * Unternehmensspezifische Personalnummer
     */
    public function getCompanyEmployeeNumber(?CompanyInterface $company = null): ?string
    {
        if (!$company) {
            // Fallback zur ersten verknüpften Company
            $firstCompany = $this->linkedCompanies()->first();
            if (!$firstCompany) {
                return null;
            }
            $company = $firstCompany;
        }
        
        // Hole die unternehmensspezifische Nummer aus den Contact-Links
        $contactLink = $this->crmContactLinks()
            ->where('company_id', $company->getCompanyId())
            ->first();
            
        return $contactLink?->company_employee_number;
    }
    
    /**
     * Setzt die unternehmensspezifische Personalnummer
     */
    public function setCompanyEmployeeNumber(CompanyInterface $company, string $employeeNumber): void
    {
        $this->crmContactLinks()
            ->where('company_id', $company->getCompanyId())
            ->update(['company_employee_number' => $employeeNumber]);
    }
    
    /**
     * Verknüpfte Unternehmen (mit spezifischer Company-Referenz)
     */
    public function getLinkedCompanies(): array
    {
        return $this->linkedCompanies()->toArray();
    }
    
    /**
     * Prüft ob der Mitarbeiter bei einem spezifischen Unternehmen angestellt ist
     */
    public function isEmployedAt(CompanyInterface $company): bool
    {
        return $this->crmContactLinks()
            ->whereHas('contact.contactRelations', function($q) use ($company) {
                $q->where('company_id', $company->getCompanyId());
            })
            ->exists();
    }
    
    /**
     * Verknüpft den Mitarbeiter mit einem Kontakt und optional einer Company
     */
    public function linkContactWithCompany(ContactInterface $contact, ?CompanyInterface $company = null): void
    {
        $this->linkContact($contact, $company);
    }
    
    /**
     * Entfernt die Verknüpfung zu einem Kontakt
     */
    public function unlinkContactFromCompany(ContactInterface $contact): void
    {
        $this->unlinkContact($contact);
    }
    
    /**
     * Beziehungen
     */
    public function crmContactLinks()
    {
        return $this->morphMany(\Platform\Crm\Models\CrmContactLink::class, 'linkable');
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
    
    public function employer()
    {
        return $this->belongsTo(HcmEmployer::class, 'employer_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(HcmEmployeeContract::class, 'employee_id');
    }

    public function healthInsuranceCompany()
    {
        return $this->belongsTo(HcmHealthInsuranceCompany::class, 'health_insurance_company_id');
    }

    public function activeContract(): ?HcmEmployeeContract
    {
        $today = now()->toDateString();
        return $this->contracts()
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->orderByDesc('start_date')
            ->first();
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
        return $query->whereHas('crmContactLinks.contact.contactRelations', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });
    }
    
    /**
     * Scope für Mitarbeiter mit unternehmensspezifischen Nummern
     */
    public function scopeWithCompanyNumbers($query, $companyId)
    {
        return $query->whereHas('crmContactLinks.contact.contactRelations', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->whereNotNull('employee_number');
    }
}
