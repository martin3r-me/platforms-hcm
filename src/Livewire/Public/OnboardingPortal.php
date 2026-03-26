<?php

namespace Platform\Hcm\Livewire\Public;

use Livewire\Component;
use Platform\Core\Models\CorePublicFormLink;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Models\HcmOnboardingContract;

class OnboardingPortal extends Component
{
    // State: loading, invalid, expired, overview, signing
    public string $state = 'loading';

    public ?int $onboardingId = null;
    public string $candidateName = '';

    // Signing state
    public ?int $activeContractId = null;
    public int $step = 1;
    public string $contractContent = '';
    public string $contractTemplateName = '';
    public string $contractTemplateCode = '';

    // §15 - Kurzfristige Beschäftigungen
    public bool $par15HasPrevious = false;
    public array $par15Entries = [];

    // §16 - Beschäftigungslose Zeiten
    public bool $par16WasJobseeking = false;
    public array $par16Entries = [];

    // Unterschrift
    public ?string $signatureData = null;

    // View-only mode (for viewing completed contracts)
    public bool $isViewOnly = false;

    public function mount(string $token): void
    {
        $link = CorePublicFormLink::where('token', $token)->first();

        if (! $link) {
            $this->state = 'invalid';
            return;
        }

        if (! $link->isValid()) {
            $this->state = 'expired';
            return;
        }

        $onboarding = $link->linkable;

        if (! $onboarding instanceof HcmOnboarding) {
            $this->state = 'invalid';
            return;
        }

        $this->onboardingId = $onboarding->id;
        $this->candidateName = $onboarding->getContact()?->full_name ?? '';

        // Auto-set pending contracts to sent when candidate opens portal
        $onboarding->onboardingContracts()
            ->where('status', 'pending')
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        $this->state = 'overview';
    }

    public function getContractsProperty()
    {
        if (! $this->onboardingId) {
            return collect();
        }

        $contracts = HcmOnboardingContract::where('hcm_onboarding_id', $this->onboardingId)
            ->with('contractTemplate')
            ->orderBy('id')
            ->get();

        foreach ($contracts as $contract) {
            $extraFields = $contract->getExtraFieldsWithLabels();
            $contract->fieldValues = collect($extraFields)
                ->filter(fn ($f) => $f['value'] !== null && $f['value'] !== '')
                ->map(fn ($f) => ['label' => $f['label'], 'value' => $f['value']])
                ->values()
                ->toArray();
        }

        return $contracts;
    }

    public function startSigning(int $contractId): void
    {
        $contract = HcmOnboardingContract::where('id', $contractId)
            ->where('hcm_onboarding_id', $this->onboardingId)
            ->first();

        if (! $contract || ! in_array($contract->status, ['sent', 'in_progress', 'completed'])) {
            return;
        }

        $this->activeContractId = $contract->id;
        $this->contractContent = $contract->personalized_content ?? '';
        $this->contractTemplateName = $contract->contractTemplate?->name ?? 'Vertrag';
        $this->contractTemplateCode = $contract->contractTemplate?->code ?? '';

        $hasPreSigningSteps = $this->contractTemplateCode === 'AV';

        if ($contract->status === 'completed') {
            $this->isViewOnly = true;
            $this->step = 3;
            $this->signatureData = $contract->signature_data;

            // Restore pre-signing data for display
            $preData = $contract->pre_signing_data ?? [];
            $this->par15HasPrevious = $preData['par15_has_previous'] ?? false;
            $this->par15Entries = $preData['par15_entries'] ?? [];
            $this->par16WasJobseeking = $preData['par16_was_jobseeking'] ?? false;
            $this->par16Entries = $preData['par16_entries'] ?? [];
        } else {
            $this->isViewOnly = false;
            $this->step = $hasPreSigningSteps ? 1 : 3;
            $this->par15HasPrevious = false;
            $this->par15Entries = [];
            $this->par16WasJobseeking = false;
            $this->par16Entries = [];
            $this->signatureData = null;
        }

        $this->state = 'signing';
    }

    public function backToOverview(): void
    {
        $this->activeContractId = null;
        $this->state = 'overview';
    }

    public function addPar15Entry(): void
    {
        $this->par15Entries[] = ['beginn' => '', 'ende' => '', 'arbeitgeber' => '', 'tage' => ''];
    }

    public function removePar15Entry(int $index): void
    {
        unset($this->par15Entries[$index]);
        $this->par15Entries = array_values($this->par15Entries);
    }

    public function addPar16Entry(): void
    {
        $this->par16Entries[] = ['beginn' => '', 'ende' => '', 'arbeitsagentur' => ''];
    }

    public function removePar16Entry(int $index): void
    {
        unset($this->par16Entries[$index]);
        $this->par16Entries = array_values($this->par16Entries);
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateStep1();
        } elseif ($this->step === 2) {
            $this->validateStep2();
        }

        $this->step++;
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function sign(): void
    {
        $this->validate([
            'signatureData' => 'required',
        ], [
            'signatureData.required' => 'Bitte unterschreiben Sie den Vertrag.',
        ]);

        $contract = HcmOnboardingContract::where('id', $this->activeContractId)
            ->where('hcm_onboarding_id', $this->onboardingId)
            ->with(['contractTemplate', 'onboarding'])
            ->first();

        if (! $contract || ! in_array($contract->status, ['sent', 'in_progress'])) {
            $this->state = 'invalid';
            return;
        }

        // Re-personalize contract with latest extra field values before signing
        if ($contract->contractTemplate) {
            $contract->personalized_content = $contract->contractTemplate->personalizeContent(
                $contract->onboarding,
                $contract
            );
        }

        $preSigningData = [
            'par15_has_previous' => $this->par15HasPrevious,
            'par15_entries' => $this->par15HasPrevious ? $this->par15Entries : [],
            'par16_was_jobseeking' => $this->par16WasJobseeking,
            'par16_entries' => $this->par16WasJobseeking ? $this->par16Entries : [],
        ];

        \Platform\Crm\Models\CommsLog::log(
            event: 'hcm_portal_sign',
            status: 'debug',
            summary: 'Pre-signing data beim Unterschreiben',
            details: json_encode([
                'contract_id' => $contract->id,
                'par15HasPrevious' => $this->par15HasPrevious,
                'par15Entries_count' => count($this->par15Entries),
                'par15Entries' => $this->par15Entries,
                'par16WasJobseeking' => $this->par16WasJobseeking,
                'par16Entries_count' => count($this->par16Entries),
                'par16Entries' => $this->par16Entries,
                'preSigningData' => $preSigningData,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        // Embed §15/§16 data at correct positions in contract (after §15/§16 sections)
        $personalizedContent = HcmOnboardingContract::embedPreSigningData(
            $contract->personalized_content ?? '',
            $preSigningData
        );

        $contract->update([
            'pre_signing_data' => $preSigningData,
            'personalized_content' => $personalizedContent,
            'signature_data' => $this->signatureData,
            'signed_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
        ]);

        $this->activeContractId = null;
        $this->state = 'overview';
    }

    private function validateStep1(): void
    {
        // Validation optional — entries are informational
    }

    private function validateStep2(): void
    {
        // Validation optional — entries are informational
    }

    public function render()
    {
        return view('hcm::livewire.public.onboarding-portal')
            ->layout('platform::layouts.guest');
    }
}
