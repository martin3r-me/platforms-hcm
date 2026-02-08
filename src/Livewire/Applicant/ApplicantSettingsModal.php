<?php

namespace Platform\Hcm\Livewire\Applicant;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmApplicantSettings;
use Platform\Hcm\Models\HcmApplicantStatus;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsChannelContext;
use Illuminate\Support\Facades\Auth;

class ApplicantSettingsModal extends Component
{
    public $modalShow = false;
    public $activeTab = 'general';

    public ?HcmApplicantSettings $settingsModel = null;
    public array $settings = [];

    public array $availableChannels = [];
    public array $linkedChannelIds = [];

    #[On('open-applicant-settings')]
    public function openSettings(): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $this->settingsModel = HcmApplicantSettings::getOrCreateForTeam($teamId);
        $this->settings = $this->settingsModel->settings ?? HcmApplicantSettings::DEFAULT_SETTINGS;

        $this->loadAvailableChannels();
        $this->loadLinkedChannels();

        $this->activeTab = 'general';
        $this->modalShow = true;
    }

    public function save(): void
    {
        $this->settingsModel->settings = $this->settings;
        $this->settingsModel->save();

        $this->modalShow = false;
    }

    public function loadAvailableChannels(): void
    {
        $team = Auth::user()->currentTeam;
        if (!$team) {
            $this->availableChannels = [];
            return;
        }

        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $this->availableChannels = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->where('type', 'email')
            ->where('is_active', true)
            ->orderBy('sender_identifier')
            ->get()
            ->toArray();
    }

    public function loadLinkedChannels(): void
    {
        if (!$this->settingsModel) {
            $this->linkedChannelIds = [];
            return;
        }

        $this->linkedChannelIds = CommsChannelContext::query()
            ->where('context_model', HcmApplicantSettings::class)
            ->where('context_model_id', $this->settingsModel->id)
            ->pluck('comms_channel_id')
            ->toArray();
    }

    public function toggleChannel(int $channelId): void
    {
        if (!$this->settingsModel) {
            return;
        }

        $existing = CommsChannelContext::query()
            ->where('comms_channel_id', $channelId)
            ->where('context_model', HcmApplicantSettings::class)
            ->where('context_model_id', $this->settingsModel->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            CommsChannelContext::create([
                'comms_channel_id' => $channelId,
                'context_model' => HcmApplicantSettings::class,
                'context_model_id' => $this->settingsModel->id,
            ]);
        }

        $this->loadLinkedChannels();
    }

    #[Computed]
    public function availableStatuses()
    {
        return HcmApplicantStatus::where('team_id', Auth::user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('hcm::livewire.applicant.applicant-settings-modal');
    }
}
