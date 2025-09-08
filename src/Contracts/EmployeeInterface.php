<?php

namespace Platform\Hcm\Contracts;

use Platform\Crm\Contracts\CompanyInterface;

interface EmployeeInterface
{
    /**
     * Eindeutige ID des Mitarbeiters
     */
    public function getEmployeeId(): int;
    
    /**
     * Mitarbeiternummer
     */
    public function getEmployeeNumber(): string;
    
    /**
     * Team-ID für den Kontext
     */
    public function getTeamId(): int;
    
    /**
     * Ist der Mitarbeiter aktiv?
     */
    public function isActive(): bool;
    
    /**
     * Verknüpfte Unternehmen (mit spezifischer Company-Referenz)
     */
    public function getLinkedCompanies(): array;
    
    /**
     * Prüft ob der Mitarbeiter bei einem spezifischen Unternehmen angestellt ist
     */
    public function isEmployedAt(CompanyInterface $company): bool;
    
    /**
     * Verknüpft den Mitarbeiter mit einem Kontakt und optional einer Company
     */
    public function linkContactWithCompany(\Platform\Crm\Contracts\ContactInterface $contact, ?CompanyInterface $company = null): void;
    
    /**
     * Entfernt die Verknüpfung zu einem Kontakt
     */
    public function unlinkContactFromCompany(\Platform\Crm\Contracts\ContactInterface $contact): void;
}
