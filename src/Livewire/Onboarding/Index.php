<?php

namespace Platform\Hcm\Livewire\Onboarding;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Core\Models\Team;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmOnboarding;
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
    public $hcm_job_title_id = null;
    public $notes = '';

    protected $rules = [
        'hcm_job_title_id' => 'nullable|integer|exists:hcm_job_titles,id',
        'notes' => 'nullable|string',
    ];

    #[Computed]
    public function onboardings()
    {
        $teamId = auth()->user()->currentTeam->id;
        $allowedTeamIds = $this->getAllowedTeamIds($teamId);

        $query = HcmOnboarding::with([
            'crmContactLinks' => fn ($q) => $q->whereIn('team_id', $allowedTeamIds),
            'crmContactLinks.contact.emailAddresses' => function ($q) {
                $q->active()
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
            'ownedByUser',
            'jobTitle',
        ])
            ->forTeam($teamId);

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
    public function availableContacts()
    {
        $alreadyLinkedContactIds = \Platform\Crm\Models\CrmContactLink::query()
            ->where('linkable_type', 'hcm_onboarding')
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

    #[Computed]
    public function availableJobTitles()
    {
        return HcmJobTitle::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($jt) => ['id' => $jt->id, 'name' => $jt->name]);
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

    public function rendered(): void
    {
        $this->dispatch('extrafields', [
            'context_type' => HcmOnboarding::class,
            'context_id' => null,
        ]);
    }

    public function render()
    {
        return view('hcm::livewire.onboarding.index')
            ->layout('platform::layouts.app');
    }

    public function createOnboarding()
    {
        $this->validate();

        $onboarding = HcmOnboarding::create([
            'notes' => $this->notes,
            'hcm_job_title_id' => $this->hcm_job_title_id ?: null,
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
            'is_active' => true,
        ]);

        if ($this->contact_id) {
            $contact = CrmContact::find($this->contact_id);
            if ($contact) {
                $onboarding->linkContact($contact);
            }
        }

        $this->resetForm();
        $this->modalShow = false;
        session()->flash('message', 'Onboarding erfolgreich erstellt.');
    }

    public function resetForm()
    {
        $this->reset(['contact_id', 'hcm_job_title_id', 'notes']);
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

    private function getAllowedTeamIds(int $teamId): array
    {
        $team = Team::find($teamId);
        if (!$team) {
            return [$teamId];
        }

        return array_merge([$teamId], $team->getAllAncestors()->pluck('id')->all());
    }
}
