<?php

namespace Platform\Hcm\Livewire\Onboarding;

use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Core\Livewire\Concerns\ResolvesAutoPilotChannel;
use Platform\Crm\Models\CommsChannel;
use Platform\Crm\Models\CommsWhatsAppThread;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Hcm\Actions\TransferOnboardingToEmployee;
use Platform\Hcm\Models\HcmOnboarding;

class Dashboard extends Component
{
    use ResolvesAutoPilotChannel;

    private function onboardingBaseQuery()
    {
        return HcmOnboarding::forTeam(auth()->user()->currentTeam->id)
            ->active();
    }

    #[Computed]
    public function onboardingCount()
    {
        return $this->onboardingBaseQuery()->count();
    }

    #[Computed]
    public function inboxOnboardings()
    {
        return $this->onboardingBaseQuery()
            ->whereNull('enrichment_status')
            ->with([
                'crmContactLinks.contact.emailAddresses',
                'crmContactLinks.contact.phoneNumbers',
                'extraFieldValues',
                'preferredCommsChannel',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function inProgressOnboardings()
    {
        $all = $this->onboardingBaseQuery()
            ->where('enrichment_status', 'enriched')
            ->with([
                'crmContactLinks.contact.emailAddresses',
                'crmContactLinks.contact.phoneNumbers',
                'extraFieldValues',
                'preferredCommsChannel',
            ])
            ->orderByDesc('created_at')
            ->get();

        return $all->filter(fn ($o) => !$this->isOnboardingCompleted($o));
    }

    #[Computed]
    public function completedOnboardings()
    {
        return $this->onboardingBaseQuery()
            ->where('enrichment_status', 'enriched')
            ->with([
                'crmContactLinks.contact.emailAddresses',
                'crmContactLinks.contact.phoneNumbers',
                'extraFieldValues',
                'preferredCommsChannel',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($o) => $this->isOnboardingCompleted($o));
    }

    #[Computed]
    public function teamChannels()
    {
        return CommsChannel::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->whereIn('type', ['email', 'whatsapp'])
            ->orderBy('type')
            ->get();
    }

    #[Computed]
    public function autoPilotProcessingIds()
    {
        return $this->inProgressOnboardings
            ->filter(fn ($o) => $o->auto_pilot && !$o->auto_pilot_completed_at)
            ->pluck('id')
            ->toArray();
    }

    #[Computed]
    public function enrichingOnboardingIds()
    {
        return $this->inboxOnboardings
            ->filter(fn ($o) => Cache::has("onboarding-enrichment:processing:{$o->id}"))
            ->pluck('id')
            ->toArray();
    }

    public function isOnboardingCompleted(HcmOnboarding $onboarding): bool
    {
        if ($onboarding->is_completed) {
            return true;
        }

        if ($onboarding->crmContactLinks->isEmpty()) {
            return false;
        }

        $extraCounts = $this->getExtraFieldCounts($onboarding);
        if ($extraCounts['total'] > 0 && $extraCounts['filled'] !== $extraCounts['total']) {
            return false;
        }

        return true;
    }

    public function getExtraFieldCounts(HcmOnboarding $onboarding): array
    {
        $fields = $onboarding->getExtraFieldsWithLabels();
        $total = count($fields);
        $filled = collect($fields)->filter(fn ($f) =>
            $f['value'] !== null && $f['value'] !== '' && $f['value'] !== []
        )->count();
        return ['filled' => $filled, 'total' => $total];
    }

    /**
     * Preload all WhatsApp threads for onboarding IDs in a single query.
     */
    #[Computed]
    public function whatsAppThreadMap(): array
    {
        $allOnboardings = collect()
            ->merge($this->inboxOnboardings)
            ->merge($this->inProgressOnboardings)
            ->merge($this->completedOnboardings);

        $onboardingIds = $allOnboardings->pluck('id')->unique()->all();
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

        // Build map: onboarding_id => thread (prefer the one with latest inbound)
        $map = [];
        foreach ($threads as $thread) {
            $oid = $thread->context_model_id;
            if (!isset($map[$oid]) || ($thread->last_inbound_at && $thread->last_inbound_at > ($map[$oid]->last_inbound_at ?? null))) {
                $map[$oid] = $thread;
            }
        }

        // Batch-load recent messages for all threads in one query
        $threadIds = collect($map)->pluck('id')->all();
        if (!empty($threadIds)) {
            $allMessages = \Platform\Crm\Models\CommsWhatsAppMessage::query()
                ->whereIn('comms_whatsapp_thread_id', $threadIds)
                ->select(['id', 'comms_whatsapp_thread_id', 'direction', 'body', 'sent_at'])
                ->orderByDesc('sent_at')
                ->get()
                ->groupBy('comms_whatsapp_thread_id');

            foreach ($map as $oid => $thread) {
                // Take last 2, then reverse for chronological order
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
                    'body' => \Illuminate\Support\Str::limit($m->body ?? '', 60),
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

    public function toggleAutoPilot(int $id): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);

        if ($onboarding->auto_pilot) {
            $onboarding->update([
                'auto_pilot' => false,
                'preferred_comms_channel_id' => null,
            ]);
        } else {
            $channel = $this->resolvePreferredChannel($onboarding);
            if ($channel) {
                $onboarding->update([
                    'auto_pilot' => true,
                    'preferred_comms_channel_id' => $channel->id,
                    'owned_by_user_id' => auth()->user()->id,
                ]);
            }
        }

        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->autoPilotProcessingIds, $this->whatsAppThreadMap);
    }

    public function markAsEnriched(int $id): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);
        $onboarding->update(['enrichment_status' => 'enriched']);
        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->onboardingCount, $this->whatsAppThreadMap);
    }

    public function transferToEmployee(int $id): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);
        $action = new TransferOnboardingToEmployee();
        $action->execute($onboarding);
        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->onboardingCount, $this->whatsAppThreadMap);
    }

    public function markAsCompleted(int $id): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);
        $onboarding->update(['is_completed' => true]);
        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->onboardingCount, $this->whatsAppThreadMap);
    }

    public function dismissOnboarding(int $id): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);
        $onboarding->update([
            'is_active' => false,
            'auto_pilot' => false,
        ]);
        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->onboardingCount, $this->whatsAppThreadMap);
    }

    public function refreshDashboard(): void
    {
        unset(
            $this->onboardingCount,
            $this->inboxOnboardings,
            $this->inProgressOnboardings,
            $this->completedOnboardings,
            $this->teamChannels,
            $this->autoPilotProcessingIds,
            $this->enrichingOnboardingIds,
            $this->whatsAppThreadMap,
        );
    }

    public function render()
    {
        return view('hcm::livewire.onboarding.dashboard')
            ->layout('platform::layouts.app');
    }
}