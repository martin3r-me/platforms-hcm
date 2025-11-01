<?php

namespace Platform\Hcm\Traits;

use Platform\Crm\Contracts\ContactInterface;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmCompany;

trait HasEmployeeContact
{
    /**
     * Beziehung zu CRM-Kontakten über polymorphe Links
     */
    public function crmContactLinks()
    {
        return $this->morphMany(
            \Platform\Crm\Models\CrmContactLink::class,
            'linkable'
        );
    }
    

    

    
    /**
     * Verknüpfte Unternehmen über Contact-Links mit Company-Referenz
     */
    public function linkedCompanies()
    {
        return $this->crmContactLinks()
            ->with(['contact'])
            ->get()
            ->map(function ($link) {
                // Hole Unternehmen über die Contact-Relations
                return $link->contact->contactRelations()
                    ->with('company')
                    ->get()
                    ->map(function ($relation) {
                        return $relation->company;
                    });
            })
            ->flatten()
            ->filter();
    }
    
    /**
     * Gibt den primären verknüpften Kontakt zurück
     */
    public function getContact(): ?ContactInterface
    {
        return $this->crmContactLinks->first()?->contact;
    }
    
    /**
     * Delegierte Accessors für Kontaktdaten
     */
    public function getFirstNameAttribute(): ?string
    {
        return $this->crmContactLinks->first()?->contact?->first_name;
    }
    
    public function getLastNameAttribute(): ?string
    {
        return $this->crmContactLinks->first()?->contact?->last_name;
    }
    
    public function getFullNameAttribute(): ?string
    {
        return $this->crmContactLinks->first()?->contact?->full_name;
    }
    
    public function getDisplayNameAttribute(): ?string
    {
        return $this->crmContactLinks->first()?->contact?->display_name;
    }
    
    public function getBirthDateAttribute(): ?\Carbon\Carbon
    {
        return $this->crmContactLinks->first()?->contact?->birth_date;
    }
    
    public function getAgeAttribute(): ?int
    {
        return $this->crmContactLinks->first()?->contact?->age;
    }
    
    /**
     * E-Mail-Adressen vom CRM-Kontakt
     */
    public function getEmailAddresses(): array
    {
        $contact = $this->crmContactLinks->first()?->contact;
        if (!$contact) {
            return [];
        }
        
        return $contact->emailAddresses()
            ->active()
            ->get()
            ->map(function ($email) {
                return [
                    'email' => $email->email_address,
                    'type' => $email->emailType?->name,
                    'is_primary' => $email->is_primary,
                ];
            })
            ->toArray();
    }
    
    /**
     * Telefonnummern vom CRM-Kontakt
     */
    public function getPhoneNumbers(): array
    {
        $contact = $this->crmContactLinks->first()?->contact;
        if (!$contact) {
            return [];
        }
        
        return $contact->phoneNumbers()
            ->active()
            ->get()
            ->map(function ($phone) {
                return [
                    'number' => $phone->international,
                    'type' => $phone->phoneType?->name,
                    'is_primary' => $phone->is_primary,
                ];
            })
            ->toArray();
    }
    
    /**
     * Adressen vom CRM-Kontakt
     */
    public function getPostalAddresses(): array
    {
        $contact = $this->crmContactLinks->first()?->contact;
        if (!$contact) {
            return [];
        }
        
        return $contact->postalAddresses()
            ->active()
            ->get()
            ->map(function ($address) {
                return [
                    'street' => $address->street,
                    'house_number' => $address->house_number,
                    'postal_code' => $address->postal_code,
                    'city' => $address->city,
                    'country' => $address->country?->name,
                    'type' => $address->addressType?->name,
                    'is_primary' => $address->is_primary,
                ];
            })
            ->toArray();
    }
    
    /**
     * Prüft ob der Mitarbeiter verknüpfte CRM-Kontakte hat
     */
    public function hasContacts(): bool
    {
        return $this->crmContactLinks()->exists();
    }
    
    /**
     * Verknüpft einen bestehenden CRM-Kontakt
     */
    public function linkContact(CrmContact $contact, ?CrmCompany $company = null): void
    {
        if (!$this->hasContacts()) {
            \Platform\Crm\Models\CrmContactLink::create([
                'contact_id' => $contact->id,
                'linkable_id' => $this->id,
                'linkable_type' => get_class($this),
                'team_id' => $this->team_id,
                'created_by_user_id' => auth()->id(),
            ]);
        }
    }
    
    /**
     * Erstellt und verknüpft einen neuen CRM-Kontakt
     */
    public function createAndLinkContact(array $contactData, ?CrmCompany $company = null): CrmContact
    {
        // Erstelle neuen CRM-Kontakt
        $contact = CrmContact::create(array_merge($contactData, [
            'team_id' => $this->team_id,
            'created_by_user_id' => auth()->id(),
        ]));
        
        // Verknüpfe mit Mitarbeiter (optional mit Company)
        $this->linkContact($contact, $company);
        
        return $contact;
    }
    
    /**
     * Entfernt eine Contact-Verknüpfung
     */
    public function unlinkContact(CrmContact $contact): void
    {
        $this->crmContactLinks()
            ->where('contact_id', $contact->id)
            ->delete();
    }
}
