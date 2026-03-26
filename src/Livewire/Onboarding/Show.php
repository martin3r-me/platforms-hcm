<?php

namespace Platform\Hcm\Livewire\Onboarding;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Core\Livewire\Concerns\WithExtraFields;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Team;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Crm\Models\CrmContact;

class Show extends Component
{
    use WithExtraFields;

    public HcmOnboarding $onboarding;

    // Kontakt-Verknüpfungs-Modals
    public $contactLinkModalShow = false;
    public $contactCreateModalShow = false;

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

    public $availableContacts = [];

    public function mount(HcmOnboarding $onboarding)
    {
        $allowedTeamIds = $this->getAllowedTeamIds($onboarding->team_id);

        $this->onboarding = $onboarding->load([
            'crmContactLinks' => fn ($q) => $q->whereIn('team_id', $allowedTeamIds),
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
            'onboardingContracts.contractTemplate',
        ]);

        $this->loadAvailableContacts();
        $this->loadExtraFieldValues($this->onboarding);
    }

    public function rules(): array
    {
        return array_merge([
            'onboarding.hcm_job_title_id' => 'nullable|integer|exists:hcm_job_titles,id',
            'onboarding.owned_by_user_id' => 'nullable|exists:users,id',
            'onboarding.notes' => 'nullable|string',
            'onboarding.is_active' => 'boolean',
        ], $this->getExtraFieldValidationRules());
    }

    public function messages(): array
    {
        return $this->getExtraFieldValidationMessages();
    }

    public function deleteOnboarding(): void
    {
        DB::transaction(function () {
            $this->onboarding->crmContactLinks()->delete();
            $this->onboarding->delete();
        });

        session()->flash('message', 'Onboarding erfolgreich gelöscht.');
        $this->redirect(route('hcm.onboardings.index'), navigate: true);
    }

    public function save(): void
    {
        $this->validate();
        $this->onboarding->save();
        $this->saveExtraFieldValues($this->onboarding);

        $this->onboarding->progress = $this->onboarding->calculateProgress();
        $this->onboarding->save();

        session()->flash('message', 'Onboarding erfolgreich aktualisiert.');
    }

    public function updatedOnboardingHcmJobTitleId($value): void
    {
        $this->onboarding->hcm_job_title_id = $value ?: null;
        $this->onboarding->load('jobTitle');
        $this->onboarding->clearExtraFieldDefinitionsCache();
        $this->loadExtraFieldValues($this->onboarding);
    }

    #[Computed]
    public function availableJobTitles()
    {
        return HcmJobTitle::where('team_id', $this->onboarding->team_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($jt) => ['id' => $jt->id, 'name' => $jt->name]);
    }

    #[Computed]
    public function teamUsers()
    {
        return Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->fullname ?? $user->name,
            ]);
    }

    #[Computed]
    public function isDirty()
    {
        return $this->onboarding->isDirty() || $this->isExtraFieldsDirty();
    }

    public function linkContact(): void
    {
        $this->contactLinkForm = [
            'contact_id' => null,
        ];
        $this->loadAvailableContacts();
        $this->contactLinkModalShow = true;
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

    public function saveContactLink(): void
    {
        $this->validate([
            'contactLinkForm.contact_id' => 'required|exists:crm_contacts,id',
        ]);

        $contact = CrmContact::find($this->contactLinkForm['contact_id']);
        $this->onboarding->linkContact($contact);

        $this->closeContactLinkModal();
        $this->onboarding->load(['crmContactLinks.contact']);
        session()->flash('message', 'Kontakt verknüpft.');
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

        $contact = CrmContact::create(array_merge($this->contactForm, [
            'team_id' => $this->onboarding->team_id,
            'created_by_user_id' => auth()->id(),
        ]));

        $this->onboarding->linkContact($contact);

        $this->closeContactCreateModal();
        $this->onboarding->load('crmContactLinks.contact');
        session()->flash('message', 'Kontakt erstellt und verknüpft.');
    }

    public function unlinkContact($contactId): void
    {
        $this->onboarding->crmContactLinks()
            ->where('contact_id', $contactId)
            ->delete();

        $this->onboarding->load('crmContactLinks.contact');
        session()->flash('message', 'Kontakt-Verknüpfung entfernt.');
    }

    public function closeContactLinkModal(): void
    {
        $this->contactLinkModalShow = false;
        $this->contactLinkForm = ['contact_id' => null];
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

    private function loadAvailableContacts(): void
    {
        $linkedContactIds = $this->onboarding->crmContactLinks->pluck('contact_id');
        $allowedTeamIds = $this->getAllowedTeamIds($this->onboarding->team_id);

        $this->availableContacts = CrmContact::active()
            ->whereIn('team_id', $allowedTeamIds)
            ->whereNotIn('id', $linkedContactIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function rendered(): void
    {
        $this->dispatch('extrafields', [
            'context_type' => get_class($this->onboarding),
            'context_id' => null,
        ]);

        $this->dispatch('tagging', [
            'context_type' => get_class($this->onboarding),
            'context_id' => $this->onboarding->id,
        ]);

        $this->dispatch('files', [
            'context_type' => get_class($this->onboarding),
            'context_id' => $this->onboarding->id,
        ]);

        $primaryContact = $this->onboarding->crmContactLinks->first()?->contact;
        $subject = 'Onboarding #' . $this->onboarding->id;
        if ($primaryContact) {
            $subject .= ' – ' . $primaryContact->full_name;
        }

        $this->dispatch('comms', [
            'model' => get_class($this->onboarding),
            'modelId' => $this->onboarding->id,
            'subject' => $subject,
            'description' => $this->onboarding->notes ?? '',
            'url' => route('hcm.onboardings.show', $this->onboarding),
            'source' => 'hcm.onboarding.view',
            'recipients' => [],
            'capabilities' => ['manage_channels' => false, 'threads' => true],
            'meta' => [
                'progress' => $this->onboarding->progress,
                'is_active' => $this->onboarding->is_active,
            ],
        ]);
    }

    public function render()
    {
        return view('hcm::livewire.onboarding.show')
            ->layout('platform::layouts.app');
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
