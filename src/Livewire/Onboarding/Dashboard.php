<?php

namespace Platform\Hcm\Livewire\Onboarding;

use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Crm\Models\CommsChannel;
use Platform\Crm\Models\CommsWhatsAppThread;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Hcm\Models\HcmOnboarding;

class Dashboard extends Component
{
    public ?string $positionFilter = null;

    public function mount(): void
    {
        $this->positionFilter = request()->query('position');
    }

    #[Computed]
    public function onboardingCount()
    {
        return HcmOnboarding::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->when($this->positionFilter, fn($q) => $q->where('source_position_title', $this->positionFilter))
            ->count();
    }

    #[Computed]
    public function inboxOnboardings()
    {
        return HcmOnboarding::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->whereNull('enrichment_status')
            ->when($this->positionFilter, fn($q) => $q->where('source_position_title', $this->positionFilter))
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
        $all = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->where('enrichment_status', 'enriched')
            ->when($this->positionFilter, fn($q) => $q->where('source_position_title', $this->positionFilter))
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
        return HcmOnboarding::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->where('enrichment_status', 'enriched')
            ->when($this->positionFilter, fn($q) => $q->where('source_position_title', $this->positionFilter))
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
    public function positionGroups()
    {
        return HcmOnboarding::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->whereNotNull('source_position_title')
            ->selectRaw('source_position_title, count(*) as count')
            ->groupBy('source_position_title')
            ->orderBy('source_position_title')
            ->pluck('count', 'source_position_title');
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
            return ['color' => 'none', 'status' => 'no_phone', 'window_open' => false];
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
            ];
        }

        $windowOpen = false;
        $morphClass = $onboarding->getMorphClass();
        $fullClass = get_class($onboarding);

        $thread = CommsWhatsAppThread::query()
            ->where(function ($q) use ($morphClass, $fullClass, $onboarding) {
                $q->where(function ($q2) use ($morphClass, $onboarding) {
                    $q2->where('context_model', $morphClass)
                        ->where('context_model_id', $onboarding->id);
                })->orWhere(function ($q2) use ($fullClass, $onboarding) {
                    $q2->where('context_model', $fullClass)
                        ->where('context_model_id', $onboarding->id);
                });
            })
            ->orderByDesc('last_inbound_at')
            ->first();

        if ($thread && $thread->isWindowOpen()) {
            $windowOpen = true;
        }

        return [
            'color' => $windowOpen ? 'green' : 'yellow',
            'status' => $whatsappStatus,
            'window_open' => $windowOpen,
        ];
    }

    public function toggleAutoPilot(int $id, string $channelType): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);

        $currentChannel = $onboarding->preferredCommsChannel;
        if ($onboarding->auto_pilot && $currentChannel?->type === $channelType) {
            $onboarding->update([
                'auto_pilot' => false,
                'preferred_comms_channel_id' => null,
            ]);
        } else {
            $channel = CommsChannel::where('team_id', auth()->user()->currentTeam->id)
                ->where('type', $channelType)
                ->where('is_active', true)
                ->first();
            if ($channel) {
                $onboarding->update([
                    'auto_pilot' => true,
                    'preferred_comms_channel_id' => $channel->id,
                    'owned_by_user_id' => auth()->user()->id,
                ]);
            }
        }

        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->autoPilotProcessingIds);
    }

    public function markAsEnriched(int $id): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);
        $onboarding->update(['enrichment_status' => 'enriched']);
        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->onboardingCount);
    }

    public function transferToEmployee(int $id): void
    {
        // Platzhalter: Onboarding → Mitarbeiter
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);
        $onboarding->update(['is_active' => false]);
        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->onboardingCount, $this->positionGroups);
    }

    public function dismissOnboarding(int $id): void
    {
        $onboarding = HcmOnboarding::forTeam(auth()->user()->currentTeam->id)->findOrFail($id);
        $onboarding->update([
            'is_active' => false,
            'auto_pilot' => false,
        ]);
        unset($this->inboxOnboardings, $this->inProgressOnboardings, $this->completedOnboardings, $this->onboardingCount, $this->positionGroups);
    }

    public function refreshDashboard(): void
    {
        unset(
            $this->onboardingCount,
            $this->inboxOnboardings,
            $this->inProgressOnboardings,
            $this->completedOnboardings,
            $this->positionGroups,
            $this->teamChannels,
            $this->autoPilotProcessingIds,
            $this->enrichingOnboardingIds,
        );
    }

    public function render()
    {
        return view('hcm::livewire.onboarding.dashboard')
            ->layout('platform::layouts.app');
    }
}
