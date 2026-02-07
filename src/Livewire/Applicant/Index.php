<?php

namespace Platform\Hcm\Livewire\Applicant;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmApplicant;
use Platform\Hcm\Models\HcmApplicantStatus;
use Platform\Crm\Models\CrmContact;

class Index extends Component
{
    // Modal State
    public $modalShow = false;

    // Search
    public $search = '';

    // Sorting
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Form Data
    public $contact_id = null;
    public $applicant_status_id = null;
    public $applied_at = null;
    public $notes = '';

    protected $rules = [
        'applicant_status_id' => 'nullable|exists:hcm_applicant_statuses,id',
        'applied_at' => 'nullable|date',
        'notes' => 'nullable|string',
    ];

    #[Computed]
    public function applicants()
    {
        $query = HcmApplicant::with([
            'crmContactLinks.contact.emailAddresses' => function ($q) {
                $q->active()
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
            'applicantStatus',
        ])
            ->forTeam(auth()->user()->currentTeam->id);

        if (!empty($this->search)) {
            $searchTerm = '%' . $this->search . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('crmContactLinks.contact', function ($contactQuery) use ($searchTerm) {
                    $contactQuery->where('last_name', 'like', $searchTerm)
                                 ->orWhere('first_name', 'like', $searchTerm)
                                 ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
                });
            });
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->get();
    }

    #[Computed]
    public function availableStatuses()
    {
        return HcmApplicantStatus::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableContacts()
    {
        // Kontakte, die bereits als Bewerber verknüpft sind
        $alreadyLinkedContactIds = \Platform\Crm\Models\CrmContactLink::query()
            ->where('linkable_type', 'hcm_applicant')
            ->whereHas('linkable', function ($q) {
                $q->where('team_id', auth()->user()->currentTeam->id);
            })
            ->pluck('contact_id');

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
        return view('hcm::livewire.applicant.index')
            ->layout('platform::layouts.app');
    }

    public function createApplicant()
    {
        $this->validate();

        $applicant = HcmApplicant::create([
            'applicant_status_id' => $this->applicant_status_id,
            'applied_at' => $this->applied_at,
            'notes' => $this->notes,
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
            'is_active' => true,
        ]);

        if ($this->contact_id) {
            $contact = CrmContact::find($this->contact_id);
            if ($contact) {
                $applicant->linkContact($contact);
            }
        }

        $this->resetForm();
        $this->modalShow = false;
        session()->flash('message', 'Bewerber erfolgreich erstellt.');
    }

    public function resetForm()
    {
        $this->reset(['contact_id', 'applicant_status_id', 'applied_at', 'notes']);
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->resetForm();
    }
}
