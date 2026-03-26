<?php

namespace Platform\Hcm\Livewire\Onboarding;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmApplicantSettings;
use Illuminate\Support\Facades\Auth;

class OnboardingSettingsModal extends Component
{
    public $modalShow = false;

    public ?HcmApplicantSettings $settingsModel = null;
    public array $settings = [];

    // Template variable mapping UI state
    public array $templateBodyParamDefs = [];

    #[On('open-onboarding-settings')]
    public function openSettings(): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $this->settingsModel = HcmApplicantSettings::getOrCreateForTeam($teamId);
        $this->settings = array_merge(HcmApplicantSettings::DEFAULT_SETTINGS, $this->settingsModel->settings ?? []);

        // Parse template params if template is already selected
        $this->loadTemplateParams();

        $this->modalShow = true;
    }

    public function updatedSettingsOnboardingWaAccountId(): void
    {
        // Reset template when account changes
        $this->settings['onboarding_wa_template_id'] = null;
        $this->settings['onboarding_wa_template_variables'] = [];
        $this->templateBodyParamDefs = [];
    }

    public function updatedSettingsOnboardingWaTemplateId(): void
    {
        $this->settings['onboarding_wa_template_variables'] = [];
        $this->loadTemplateParams();
    }

    public function save(): void
    {
        $this->settingsModel->settings = $this->settings;
        $this->settingsModel->save();
        $this->modalShow = false;
    }

    #[Computed]
    public function availableWhatsAppAccounts(): array
    {
        if (!class_exists(\Platform\Integrations\Models\IntegrationsWhatsAppAccount::class)) {
            return [];
        }

        return \Platform\Integrations\Models\IntegrationsWhatsAppAccount::query()
            ->withCount('templates')
            ->orderBy('title')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'label' => "{$a->title} ({$a->phone_number})",
            ])
            ->toArray();
    }

    #[Computed]
    public function availableWhatsAppTemplates(): array
    {
        if (!class_exists(\Platform\Integrations\Models\IntegrationsWhatsAppTemplate::class)) {
            return [];
        }

        $query = \Platform\Integrations\Models\IntegrationsWhatsAppTemplate::query()
            ->with('whatsappAccount:id,title,phone_number')
            ->where('status', 'APPROVED');

        $accountId = $this->settings['onboarding_wa_account_id'] ?? null;
        if ($accountId) {
            $query->where('whatsapp_account_id', (int) $accountId);
        }

        return $query->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'label' => "{$t->name} ({$t->language})" . (!$accountId && $t->whatsappAccount ? " — {$t->whatsappAccount->title}" : ''),
            ])
            ->toArray();
    }

    #[Computed]
    public function variableSources(): array
    {
        return HcmApplicantSettings::ONBOARDING_VARIABLE_SOURCES;
    }

    private function loadTemplateParams(): void
    {
        $templateId = $this->settings['onboarding_wa_template_id'] ?? null;
        if (!$templateId || !class_exists(\Platform\Integrations\Models\IntegrationsWhatsAppTemplate::class)) {
            $this->templateBodyParamDefs = [];
            return;
        }

        $template = \Platform\Integrations\Models\IntegrationsWhatsAppTemplate::find($templateId);
        if (!$template) {
            $this->templateBodyParamDefs = [];
            return;
        }

        $this->templateBodyParamDefs = $this->parseTemplateBodyParams($template->components ?? []);
    }

    private function parseTemplateBodyParams(array $components): array
    {
        $params = [];
        foreach ($components as $component) {
            if (($component['type'] ?? '') !== 'BODY') {
                continue;
            }

            $text = $component['text'] ?? '';
            $examplesByName = [];
            $namedParams = $component['example']['body_text_named_params'] ?? [];
            foreach ($namedParams as $np) {
                $examplesByName[$np['param_name']] = $np['example'] ?? '';
            }
            $positionalExamples = $component['example']['body_text'][0] ?? [];

            preg_match_all('/\{\{(\w+)\}\}/', $text, $matches);

            foreach ($matches[1] as $i => $paramName) {
                $params[] = [
                    'name' => $paramName,
                    'example' => $examplesByName[$paramName] ?? $positionalExamples[$i] ?? '',
                ];
            }
        }
        return $params;
    }

    public function render()
    {
        return view('hcm::livewire.onboarding.onboarding-settings-modal');
    }
}
