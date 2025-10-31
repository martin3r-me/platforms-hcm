<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Traits\Encryptable;
use Platform\Hcm\Contracts\EmployeeInterface;
use Platform\Hcm\Traits\HasEmployeeContact;
use Platform\Crm\Contracts\CompanyInterface;
use Platform\Crm\Contracts\ContactInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class HcmEmployee extends Model implements EmployeeInterface
{
    use LogsActivity, HasEmployeeContact, Encryptable;
    
    protected $table = 'hcm_employees';
    
    protected $fillable = [
        'uuid',
        'employee_number',
        'employer_id', // FK zu HcmEmployer
        'company_employee_number', // Unternehmensspezifische Personalnummer
        'health_insurance_company_id', // FK zu HcmHealthInsuranceCompany
        'schooling_level',
        'vocational_training_level',
        'created_by_user_id',
        'owned_by_user_id',
        'team_id',
        'is_active',
        // Core person fields
        'birth_date',
        'gender',
        'nationality',
        'children_count',
        'disability_degree',
        'tax_class',
        'church_tax',
        'tax_id_number',
        'child_allowance',
        'insurance_status',
        'payout_type',
        'payout_method_id',
        'bank_account_holder',
        'bank_iban',
        'bank_swift',
        'health_insurance_ik',
        'health_insurance_name',
        // Phase 1: Notfallkontakt
        'emergency_contact_name',
        'emergency_contact_phone',
        // Phase 1: Zusätzliche Personaldaten
        'birth_surname',
        'birth_place',
        'birth_country',
        'title',
        'name_prefix',
        'name_suffix',
        // Phase 1: Arbeitserlaubnis
        'permanent_residence_permit',
        'work_permit_until',
        'border_worker_country',
        // Phase 1: Behinderung Details
        'has_disability_id',
        'disability_id_number',
        'disability_id_valid_from',
        'disability_id_valid_until',
        'disability_office',
        'disability_office_location',
        // Phase 1: Vorgesetzter/Organisation
        'supervisor_id',
        'deputy_id',
        'alias',
        // Phase 1: Schulungen
        'hygiene_training_date',
        'parent_eligibility_proof_date',
        // Phase 1: Sonstiges
        'business_email',
        'web_time_pin',
        'alternative_employee_number',
        'is_seasonal_worker',
        'is_disability_pensioner',
        'attributes'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'schooling_level' => 'integer',
        'vocational_training_level' => 'integer',
        'birth_date' => 'date',
        'children_count' => 'integer',
        'disability_degree' => 'integer',
        'child_allowance' => 'integer',
        'permanent_residence_permit' => 'boolean',
        'work_permit_until' => 'date',
        'has_disability_id' => 'boolean',
        'disability_id_valid_from' => 'date',
        'disability_id_valid_until' => 'date',
        'hygiene_training_date' => 'date',
        'parent_eligibility_proof_date' => 'date',
        'is_seasonal_worker' => 'boolean',
        'is_disability_pensioner' => 'boolean',
        'attributes' => 'array',
    ];

    protected array $encryptable = [
        'tax_id_number' => 'string',
        'bank_iban' => 'string',
        'bank_swift' => 'string',
        'bank_account_holder' => 'string',
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

    public function issues()
    {
        return $this->hasMany(HcmEmployeeIssue::class, 'employee_id');
    }

    public function benefits()
    {
        return $this->hasMany(HcmEmployeeBenefit::class, 'employee_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(HcmEmployeeTraining::class, 'employee_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(HcmEmployee::class, 'supervisor_id');
    }

    public function deputy()
    {
        return $this->belongsTo(HcmEmployee::class, 'deputy_id');
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
