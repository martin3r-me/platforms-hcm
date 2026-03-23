<?php

namespace Platform\Hcm\Livewire\InterviewBookings;

use Livewire\Component;
use Livewire\Attributes\Computed;
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
}
