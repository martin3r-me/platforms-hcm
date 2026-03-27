<?php

namespace Platform\Hcm\Livewire\Onboarding;

use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Core\Models\Team;
use Platform\Crm\Models\CommsWhatsAppThread;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmOnboarding;

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
            'crmContactLinks.contact.phoneNumbers',
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

    #[Computed]
    public function whatsAppThreadMap(): array
    {
        $onboardingIds = $this->onboardings->pluck('id')->unique()->all();
        if (empty($onboardingIds)) {
            return [];
        }

        $morphClass = (new HcmOnboarding)->getMorphClass();
        $fullClass = HcmOnboarding::class;

        $threads = CommsWhatsAppThread::query()
            ->where(function ($q) use ($morphClass, $fullClass, $onboardingIds) {
                $q->where(function ($q2) use ($morphClass, $onboardingIds) {
                    $q2->where('context_model', $morphClass)
                        ->whereIn('context_model_id', $onboardingIds);
                })->orWhere(function ($q2) use ($fullClass, $onboardingIds) {
                    $q2->where('context_model', $fullClass)
                        ->whereIn('context_model_id', $onboardingIds);
                });
            })
            ->get();

        $map = [];
        foreach ($threads as $thread) {
            $oid = $thread->context_model_id;
            if (!isset($map[$oid]) || ($thread->last_inbound_at && $thread->last_inbound_at > ($map[$oid]->last_inbound_at ?? null))) {
                $map[$oid] = $thread;
            }
        }

        $threadIds = collect($map)->pluck('id')->all();
        if (!empty($threadIds)) {
            $allMessages = \Platform\Crm\Models\CommsWhatsAppMessage::query()
                ->whereIn('comms_whatsapp_thread_id', $threadIds)
                ->select(['id', 'comms_whatsapp_thread_id', 'direction', 'body', 'sent_at'])
                ->orderByDesc('sent_at')
                ->get()
                ->groupBy('comms_whatsapp_thread_id');

            foreach ($map as $oid => $thread) {
                $msgs = ($allMessages->get($thread->id) ?? collect())->take(2)->reverse()->values();
                $thread->setRelation('recentMessages', $msgs);
            }
        }

        return $map;
    }

    public function getWhatsAppStatus(HcmOnboarding $onboarding): array
    {
        $phoneNumber = null;
        $whatsappStatus = CrmPhoneNumber::WHATSAPP_UNKNOWN;

        foreach ($onboarding->crmContactLinks as $link) {
            foreach ($link->contact?->phoneNumbers ?? [] as $phone) {
                if (!$phone->is_active) continue;
                $phoneNumber = $phone->international ?: $phone->raw_input;
                $whatsappStatus = $phone->whatsapp_status ?? CrmPhoneNumber::WHATSAPP_UNKNOWN;
                if ($whatsappStatus !== CrmPhoneNumber::WHATSAPP_UNKNOWN) {
                    break 2;
                }
            }
        }

        if (!$phoneNumber) {
            return ['color' => 'none', 'status' => 'no_phone', 'window_open' => false, 'last_message' => null, 'recent_messages' => []];
        }

        $isWhatsAppAvailable = in_array($whatsappStatus, [
            CrmPhoneNumber::WHATSAPP_AVAILABLE,
            CrmPhoneNumber::WHATSAPP_OPTED_IN,
        ]);

        if (!$isWhatsAppAvailable) {
            return [
                'color' => 'gray',
                'status' => $whatsappStatus,
                'window_open' => false,
                'last_message' => null,
                'recent_messages' => [],
            ];
        }

        $windowOpen = false;
        $thread = $this->whatsAppThreadMap[$onboarding->id] ?? null;

        $lastMessage = null;
        $recentMessages = [];
        if ($thread) {
            if ($thread->isWindowOpen()) {
                $windowOpen = true;
            }
            $lastMessage = $thread->last_message_preview;

            $recentMessages = ($thread->recentMessages ?? collect())
                ->map(fn ($m) => [
                    'direction' => $m->direction,
                    'body' => Str::limit($m->body ?? '', 60),
                    'at' => $m->sent_at?->format('d.m. H:i'),
                ])
                ->values()
                ->all();
        }

        return [
            'color' => $windowOpen ? 'green' : 'yellow',
            'status' => $whatsappStatus,
            'window_open' => $windowOpen,
            'last_message' => $lastMessage,
            'recent_messages' => $recentMessages,
        ];
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
