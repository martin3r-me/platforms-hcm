<?php

namespace Platform\Hcm\Livewire\InterviewBookings;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Crm\Models\CommsChannel;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Crm\Services\Comms\WhatsAppMetaService;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Models\HcmInterviewBooking;
use Platform\Hcm\Models\HcmOnboarding;

class Index extends Component
{
    public $interviewId;
    public $search = '';
    public $filterStatus = 'all';

    public $showBookModal = false;
    public $selectedOnboardingId = '';
    public $bookingNotes = '';

    public function mount(int $interview)
    {
        $this->interviewId = $interview;
    }

    public function render()
    {
        return view('hcm::livewire.interview-bookings.index')
            ->layout('platform::layouts.app');
    }

    #[Computed]
    public function interview()
    {
        return HcmInterview::with(['interviewType', 'jobTitle', 'interviewers'])
            ->findOrFail($this->interviewId);
    }

    #[Computed]
    public function bookings()
    {
        return HcmInterviewBooking::where('hcm_interview_id', $this->interviewId)
            ->when($this->search, function ($q) {
                $q->whereHas('onboarding.crmContactLinks.contact', function ($query) {
                    $query->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('status', $this->filterStatus))
            ->with(['onboarding.crmContactLinks.contact'])
            ->orderBy('booked_at', 'desc')
            ->get();
    }

    #[Computed]
    public function availableOnboardings()
    {
        $teamId = auth()->user()->currentTeam->id;
        $bookedIds = HcmInterviewBooking::where('hcm_interview_id', $this->interviewId)
            ->pluck('hcm_onboarding_id');

        $query = HcmOnboarding::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNotIn('id', $bookedIds);

        if ($this->interview->hcm_job_title_id) {
            $query->where('hcm_job_title_id', $this->interview->hcm_job_title_id);
        }

        return $query->with(['crmContactLinks.contact'])
            ->get();
    }

    public function openBookModal(): void
    {
        $this->selectedOnboardingId = '';
        $this->bookingNotes = '';
        $this->showBookModal = true;
    }

    public function book(): void
    {
        $this->validate([
            'selectedOnboardingId' => 'required|integer|exists:hcm_onboardings,id',
            'bookingNotes' => 'nullable|string',
        ]);

        $interview = $this->interview;

        if ($interview->max_participants) {
            $currentCount = HcmInterviewBooking::where('hcm_interview_id', $this->interviewId)
                ->whereNotIn('status', ['cancelled'])
                ->count();

            if ($currentCount >= $interview->max_participants) {
                session()->flash('error', 'Maximale Teilnehmerzahl erreicht!');
                return;
            }
        }

        $existing = HcmInterviewBooking::where('hcm_interview_id', $this->interviewId)
            ->where('hcm_onboarding_id', $this->selectedOnboardingId)
            ->exists();

        if ($existing) {
            session()->flash('error', 'Dieser Kandidat ist bereits gebucht!');
            return;
        }

        HcmInterviewBooking::create([
            'hcm_interview_id' => $this->interviewId,
            'hcm_onboarding_id' => $this->selectedOnboardingId,
            'status' => 'registered',
            'notes' => $this->bookingNotes ?: null,
            'booked_at' => now(),
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
        ]);

        session()->flash('success', 'Kandidat erfolgreich gebucht!');
        $this->showBookModal = false;
        $this->selectedOnboardingId = '';
        $this->bookingNotes = '';
    }

    public function updateStatus(int $bookingId, string $status): void
    {
        $validStatuses = ['registered', 'confirmed', 'attended', 'cancelled', 'no_show'];
        if (!in_array($status, $validStatuses)) {
            return;
        }

        $booking = HcmInterviewBooking::findOrFail($bookingId);
        $booking->update(['status' => $status]);
        session()->flash('success', 'Status aktualisiert!');
    }

    public function deleteBooking(int $bookingId): void
    {
        $booking = HcmInterviewBooking::findOrFail($bookingId);
        $booking->delete();
        session()->flash('success', 'Buchung erfolgreich gelöscht!');
    }

    public function sendReminder(int $bookingId): void
    {
        $booking = HcmInterviewBooking::with(['onboarding.crmContactLinks.contact.phoneNumbers'])
            ->findOrFail($bookingId);

        $interview = $this->interview;

        if (!$interview->reminder_wa_template_id) {
            session()->flash('error', 'Kein WhatsApp-Template am Termin konfiguriert.');
            return;
        }

        if (!class_exists(\Platform\Integrations\Models\IntegrationsWhatsAppTemplate::class)) {
            session()->flash('error', 'WhatsApp-Integrations-Modul nicht verfügbar.');
            return;
        }

        $template = \Platform\Integrations\Models\IntegrationsWhatsAppTemplate::find($interview->reminder_wa_template_id);
        if (!$template || $template->status !== 'APPROVED') {
            session()->flash('error', 'Template nicht gefunden oder nicht freigegeben.');
            return;
        }

        $channel = $this->resolveWhatsAppChannel($template);
        if (!$channel) {
            session()->flash('error', 'Kein aktiver WhatsApp-Kanal gefunden.');
            return;
        }

        $phoneNumber = $this->findPhoneNumber($booking);
        if (!$phoneNumber) {
            session()->flash('error', 'Keine Telefonnummer für diesen Kandidaten gefunden.');
            return;
        }

        try {
            $components = $interview->resolveTemplateComponents(
                $template->components ?? [],
                $booking,
            );

            $service = app(WhatsAppMetaService::class);
            $message = $service->sendTemplate(
                channel: $channel,
                to: $phoneNumber->international,
                templateName: $template->name,
                components: $components,
                languageCode: $template->language ?? 'de',
                sender: auth()->user(),
            );

            // Link thread to onboarding context
            if ($message->thread && $booking->onboarding) {
                $message->thread->addContext(
                    get_class($booking->onboarding),
                    $booking->onboarding->id,
                    'interview_reminder',
                );
            }

            $booking->update(['reminder_sent_at' => now()]);
            session()->flash('success', 'Erinnerung gesendet an ' . $phoneNumber->international);
        } catch (\Throwable $e) {
            session()->flash('error', 'Versand fehlgeschlagen: ' . $e->getMessage());
        }
    }

    private function resolveWhatsAppChannel($template): ?CommsChannel
    {
        $account = $template->whatsappAccount;
        if (!$account || !$account->active) {
            return null;
        }

        return CommsChannel::where('type', 'whatsapp')
            ->where('is_active', true)
            ->where('sender_identifier', $account->phone_number)
            ->first();
    }

    private function findPhoneNumber(HcmInterviewBooking $booking): ?CrmPhoneNumber
    {
        $onboarding = $booking->onboarding;
        if (!$onboarding) {
            return null;
        }

        foreach ($onboarding->crmContactLinks as $link) {
            $contact = $link->contact;
            if (!$contact) {
                continue;
            }

            $primary = $contact->phoneNumbers
                ->where('is_active', true)
                ->where('is_primary', true)
                ->whereNotNull('international')
                ->first();

            if ($primary) {
                return $primary;
            }

            $fallback = $contact->phoneNumbers
                ->where('is_active', true)
                ->whereNotNull('international')
                ->first();

            if ($fallback) {
                return $fallback;
            }
        }

        return null;
    }
}
