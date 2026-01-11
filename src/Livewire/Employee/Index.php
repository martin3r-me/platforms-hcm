<?php

namespace Platform\Hcm\Livewire\Employee;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Crm\Models\CrmContact;

class Index extends Component
{
    use WithPagination;

    // Modal State
    public $modalShow = false;
    
    // Search
    public $search = '';
    
    // Sorting
    public $sortField = 'employee_number';
    public $sortDirection = 'asc';
    
    // Form Data
    public $employer_id = null;
    public $employee_number = '';
    public $contact_id = null;

    // Wizard State
    public $createStep = 1;

    protected $rules = [
        'employer_id' => 'required|exists:hcm_employers,id',
    ];

    #[Computed]
    public function employees()
    {
        $query = HcmEmployee::with([
            'employer', 
            'crmContactLinks.contact.emailAddresses' => function ($q) {
                $q->active()
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
            'crmContactLinks.contact.phoneNumbers' => function ($q) {
                $q->active()
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
            'contracts' => function ($q) {
                $q->orderBy('start_date', 'desc');
            },
            'contracts.jobTitles',
            'contracts.jobActivities',
            'contracts.tariffGroup',
            'contracts.tariffLevel',
            'contracts.jobActivityAlias'
        ])
            ->forTeam(auth()->user()->currentTeam->id);

        // Suche nach Personalnummer oder Nachname
        if (!empty($this->search)) {
            $searchTerm = '%' . $this->search . '%';
            
            $query->where(function ($q) use ($searchTerm) {
                // Suche nach Personalnummer (employee_number oder alternative_employee_number)
                $q->where('employee_number', 'like', $searchTerm)
                  ->orWhere('alternative_employee_number', 'like', $searchTerm)
                  // Suche nach Nachname über verknüpfte Kontakte
                  ->orWhereHas('crmContactLinks.contact', function ($contactQuery) use ($searchTerm) {
                      $contactQuery->where('last_name', 'like', $searchTerm)
                                   ->orWhere('first_name', 'like', $searchTerm)
                                   ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
                  });
            });
        }

        if ($this->sortField === 'employee_number') {
            $query->orderBy('employee_number', $this->sortDirection);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $employees = $query->get();
        
        // Stelle sicher, dass alle jobActivities geladen sind
        foreach ($employees as $employee) {
            foreach ($employee->contracts as $contract) {
                // Lade jobActivities nach, falls sie nicht geladen wurden
                if (!$contract->relationLoaded('jobActivities')) {
                    $contract->load('jobActivities');
                }
            }
        }
        
        return $employees;
    }

    #[Computed]
    public function availableEmployers()
    {
        return HcmEmployer::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->orderBy('employer_number')
            ->get();
    }

    #[Computed]
    public function availableContacts()
    {
        // Ohne gewählten Arbeitgeber keine Kontakte anbieten
        if (!$this->employer_id) {
            return collect();
        }

        // Alle Mitarbeiter des gewählten Arbeitgebers
        $employeeIds = HcmEmployee::query()
            ->where('employer_id', $this->employer_id)
            ->forTeam(auth()->user()->currentTeam->id)
            ->pluck('id');

        // Kontakte, die bereits über ContactLinks an Mitarbeiter dieses Arbeitgebers hängen
        $alreadyLinkedContactIds = \Platform\Crm\Models\CrmContactLink::query()
            ->where('linkable_type', HcmEmployee::class)
            ->whereIn('linkable_id', $employeeIds)
            ->pluck('contact_id');

        // Nur noch nicht verknüpfte, team-lokale, aktive Kontakte anzeigen
        return CrmContact::active()
            ->where('team_id', auth()->user()->currentTeam->id)
            ->whereNotIn('id', $alreadyLinkedContactIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }


    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        return view('hcm::livewire.employee.index')
            ->layout('platform::layouts.app');
    }


    public function createEmployee()
    {
        $this->validate();
        
        // Hole den Arbeitgeber und generiere die nächste Mitarbeiter-Nummer
        $employer = HcmEmployer::find($this->employer_id);
        if (!$employer) {
            session()->flash('error', 'Arbeitgeber nicht gefunden!');
            return;
        }
        
        $employeeNumber = $employer->generateNextEmployeeNumber();
        
        $employee = HcmEmployee::create([
            'employer_id' => $this->employer_id,
            'employee_number' => $employeeNumber,
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
            'is_active' => true,
        ]);

        $this->resetForm();
        $this->modalShow = false;
        
        session()->flash('message', "Mitarbeiter erfolgreich erstellt! Nummer: {$employeeNumber}");
    }

    public function finalizeCreateEmployee()
    {
        $this->validate();

        $employer = HcmEmployer::find($this->employer_id);
        if (!$employer) {
            session()->flash('error', 'Arbeitgeber nicht gefunden!');
            return;
        }

        $employeeNumber = $employer->generateNextEmployeeNumber();

        $employee = HcmEmployee::create([
            'employer_id' => $this->employer_id,
            'employee_number' => $employeeNumber,
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
            'is_active' => true,
        ]);

        // Optional: ausgewählten CRM-Kontakt verknüpfen (keine Auto-Auswahl)
        if ($this->contact_id) {
            $contact = CrmContact::find($this->contact_id);
            if ($contact) {
                $employee->linkContact($contact);
            }
        }

        $this->resetForm();
        $this->modalShow = false;
        session()->flash('message', "Mitarbeiter erfolgreich erstellt! Nummer: {$employeeNumber}");
    }

    public function resetForm()
    {
        $this->reset(['employer_id', 'employee_number', 'contact_id']);
        $this->createStep = 1;
    }

    public function openCreateModal()
    {
        // Setze den ersten verfügbaren Arbeitgeber als Standard
        $firstEmployer = $this->availableEmployers->first();
        if ($firstEmployer) {
            $this->employer_id = $firstEmployer->id;
        }
        
        $this->contact_id = null;
        $this->createStep = 1;
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->resetForm();
    }

    public function updatedEmployerId()
    {
        // Kontakt-Auswahl zurücksetzen wenn der Arbeitgeber gewechselt wird
        $this->contact_id = null;
        // Bei Bedarf wieder auf Schritt 1 zurückspringen
        if ($this->createStep > 1) {
            $this->createStep = 2; // im Wizard bleiben, nur Auswahl zurücksetzen
        }
    }

    public function nextStep()
    {
        if ($this->createStep === 1) {
            $this->validate();
            $this->createStep = 2;
        }
    }

    public function prevStep()
    {
        if ($this->createStep > 1) {
            $this->createStep = 1;
        }
    }
}

