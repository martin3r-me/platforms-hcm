<?php

namespace Platform\Hcm\Livewire\Employee;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmCompany;

class Employee extends Component
{
    public HcmEmployee $employee;

    // Kontakt-Verknüpfungs-Modals
    public $contactLinkModalShow = false;
    public $contactCreateModalShow = false;
    
    // Company-Auswahl-Modal (wenn Contact mehrere Companies hat)
    public $companySelectionModalShow = false;
    public $selectedContactForCompanySelection = null;
    public $availableCompaniesForSelection = [];
    
    // Kontakt-Form
    public $contactForm = [
        'first_name' => '',
        'last_name' => '',
        'middle_name' => '',
        'nickname' => '',
        'birth_date' => '',
        'notes' => '',
    ];
    
    // Kontakt-Auswahl-Form
    public $contactLinkForm = [
        'contact_id' => null,
    ];
    
    // Company-Auswahl-Form
    public $companySelectionForm = [
        'company_id' => null,
    ];
    
    public $availableContacts = [];

    public function mount(HcmEmployee $employee)
    {
        $this->employee = $employee->load([
            'crmContactLinks.contact.emailAddresses',
            'crmContactLinks.contact.phoneNumbers',
            'employer', 
            'activities',
            'contracts' => function($q) {
                $q->orderBy('start_date', 'desc');
            },
            'contracts.jobTitles',
            'contracts.jobActivities',
            'contracts.tariffGroup',
            'contracts.tariffLevel'
        ]);
        
        // Verfügbare Kontakte für Verknüpfung laden
        $this->loadAvailableContacts();
    }

    public function rules(): array
    {
        return [
            'employee.employee_number' => 'required|string|max:255|unique:hcm_employees,employee_number,' . $this->employee->id,
        ];
    }

    public function save(): void
    {
        $this->validate();
        $this->employee->save();

        session()->flash('message', 'Mitarbeiter erfolgreich aktualisiert.');
    }

    public function addContact(): void
    {
        $this->contactForm = [
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'nickname' => '',
            'birth_date' => '',
            'notes' => '',
        ];
        $this->contactCreateModalShow = true;
    }

    public function linkContact(): void
    {
        $this->contactLinkForm = [
            'contact_id' => null,
        ];
        $this->loadAvailableContacts();
        $this->contactLinkModalShow = true;
    }

    public function saveContact(): void
    {
        $this->validate([
            'contactForm.first_name' => 'required|string|max:255',
            'contactForm.last_name' => 'required|string|max:255',
            'contactForm.middle_name' => 'nullable|string|max:255',
            'contactForm.nickname' => 'nullable|string|max:255',
            'contactForm.birth_date' => 'nullable|date',
            'contactForm.notes' => 'nullable|string|max:1000',
        ]);

        // Erstelle neuen CRM-Kontakt
        $contact = CrmContact::create(array_merge($this->contactForm, [
            'team_id' => $this->employee->team_id,
            'created_by_user_id' => auth()->id(),
        ]));

        // Verknüpfe mit Mitarbeiter
        $this->employee->linkContact($contact);

        $this->closeContactCreateModal();
        $this->employee->load('crmContactLinks.contact');
        
        session()->flash('message', 'Kontakt erstellt und verknüpft.');
    }

    public function saveContactLink(): void
    {
        $this->validate([
            'contactLinkForm.contact_id' => 'required|exists:crm_contacts,id',
        ]);

        $contact = CrmContact::find($this->contactLinkForm['contact_id']);
        
        // Prüfe, ob der Contact mehrere Companies hat
        $companies = $contact->contactRelations()->with('company')->get();
        
        if ($companies->count() > 1) {
            // Zeige Company-Auswahl-Modal
            $this->selectedContactForCompanySelection = $contact;
            $this->availableCompaniesForSelection = $companies;
            $this->companySelectionForm = ['company_id' => null];
            $this->companySelectionModalShow = true;
            $this->closeContactLinkModal();
        } else {
            // Direkt verknüpfen (nur eine oder keine Company)
            $company = $companies->first()?->company;
            $this->employee->linkContact($contact, $company);
            $this->closeContactLinkModal();
            $this->employee->load(['crmContactLinks.contact']);
            session()->flash('message', 'Kontakt verknüpft.');
        }
    }

    public function saveCompanySelection(): void
    {
        $this->validate([
            'companySelectionForm.company_id' => 'required|exists:crm_companies,id',
        ]);

        // Verknüpfe den Contact mit dem ausgewählten Unternehmen
        $selectedCompany = CrmCompany::find($this->companySelectionForm['company_id']);
        $this->employee->linkContact($this->selectedContactForCompanySelection, $selectedCompany);
        
                    $this->closeCompanySelectionModal();
            $this->employee->load(['crmContactLinks.contact']);
        
        session()->flash('message', "Kontakt verknüpft mit Unternehmen: {$selectedCompany->name}");
    }

    public function closeCompanySelectionModal(): void
    {
        $this->companySelectionModalShow = false;
        $this->selectedContactForCompanySelection = null;
        $this->availableCompaniesForSelection = [];
        $this->companySelectionForm = ['company_id' => null];
    }

    public function unlinkContact($contactId): void
    {
        $this->employee->crmContactLinks()
            ->where('contact_id', $contactId)
            ->delete();
        
        $this->employee->load('crmContactLinks.contact');
        session()->flash('message', 'Kontakt-Verknüpfung entfernt.');
    }

    public function closeContactCreateModal(): void
    {
        $this->contactCreateModalShow = false;
        $this->contactForm = [
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'nickname' => '',
            'birth_date' => '',
            'notes' => '',
        ];
    }

    public function closeContactLinkModal(): void
    {
        $this->contactLinkModalShow = false;
        $this->contactLinkForm = [
            'contact_id' => null,
        ];
    }

    private function loadAvailableContacts(): void
    {
        // Lade alle verfügbaren Kontakte, die noch nicht mit diesem Mitarbeiter verknüpft sind
        $linkedContactIds = $this->employee->crmContactLinks->pluck('contact_id');
        
        $this->availableContacts = CrmContact::active()
            ->whereNotIn('id', $linkedContactIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->employee),
            'modelId' => $this->employee->id,
            'subject' => $this->employee->employee_number,
            'description' => '',
            'url' => route('hcm.employees.show', $this->employee),
            'source' => 'hcm.employees.view'
        ]);
    }

    /**
     * Prüft, ob es ungespeicherte Änderungen gibt
     */
    #[Computed]
    public function isDirty()
    {
        return $this->employee->isDirty();
    }

    public function addContract(): void
    {
        // TODO: Implement contract creation modal
        session()->flash('message', 'Vertragserstellung wird implementiert.');
    }

    public function editContract($contractId): void
    {
        // TODO: Implement contract editing modal
        session()->flash('message', 'Vertragsbearbeitung wird implementiert.');
    }

    public function render()
    {
        return view('hcm::livewire.employee.employee')
            ->layout('platform::layouts.app');
    }
}

