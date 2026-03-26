<?php

namespace Platform\Hcm\Livewire\Onboarding;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Core\Livewire\Concerns\WithExtraFields;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Team;
use Platform\Crm\Models\CommsChannel;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Services\Comms\WhatsAppMetaService;
use Platform\Crm\Models\CommsLog;
use Platform\Hcm\Models\HcmApplicantSettings;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Models\HcmContractTemplate;
use Platform\Hcm\Models\HcmOnboardingContract;

class Show extends Component
{
    use WithExtraFields;

    public HcmOnboarding $onboarding;

    // Vertrag zuweisen
    public $assignContractModalShow = false;
    public $selectedTemplateId = null;

    // Vertrag versenden
    public ?string $contractLinkUrl = null;

    // Portal-Link
    public ?string $portalLinkUrl = null;

    // Contract Extra Fields Modal
    public bool $contractFieldsModalShow = false;
    public ?int $activeContractId = null;
    public array $contractFieldDefinitions = [];
    public array $contractFieldValues = [];

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
            'publicFormLink',
        ]);

        $this->loadAvailableContacts();
        $this->loadExtraFieldValues($this->onboarding);

        // Auto-generate portal link when contracts exist
        if ($this->onboarding->onboardingContracts->isNotEmpty()) {
            $link = $this->onboarding->publicFormLink ?? $this->onboarding->getOrCreatePublicFormLink();
            $this->portalLinkUrl = route('hcm.public.onboarding-portal', ['token' => $link->token]);
        }
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

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Onboarding erfolgreich gelöscht.']);
        $this->redirect(route('hcm.onboardings.index'), navigate: true);
    }

    public function save(): void
    {
        $this->validate();
        $this->onboarding->save();
        $this->saveExtraFieldValues($this->onboarding);

        $this->onboarding->progress = $this->onboarding->calculateProgress();
        $this->onboarding->save();

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Onboarding erfolgreich aktualisiert.']);
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
    public function availableTemplates()
    {
        return HcmContractTemplate::where('team_id', $this->onboarding->team_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]);
    }

    public function openAssignContractModal(): void
    {
        $this->selectedTemplateId = null;
        $this->assignContractModalShow = true;
    }

    public function closeAssignContractModal(): void
    {
        $this->assignContractModalShow = false;
        $this->selectedTemplateId = null;
    }

    public function assignContract(): void
    {
        $this->validate([
            'selectedTemplateId' => 'required|exists:hcm_contract_templates,id',
        ]);

        $template = HcmContractTemplate::findOrFail($this->selectedTemplateId);
        $personalizedContent = $template->personalizeContent($this->onboarding);

        HcmOnboardingContract::create([
            'hcm_onboarding_id' => $this->onboarding->id,
            'hcm_contract_template_id' => $template->id,
            'team_id' => $this->onboarding->team_id,
            'personalized_content' => $personalizedContent,
            'status' => 'pending',
            'created_by_user_id' => auth()->id(),
        ]);

        $this->onboarding->load('onboardingContracts.contractTemplate');
        $this->closeAssignContractModal();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Vertrag erfolgreich zugewiesen.']);
    }

    public function toggleCompleted(): void
    {
        $this->onboarding->is_completed = !$this->onboarding->is_completed;
        $this->onboarding->save();

        $this->dispatch('notify', ['type' => 'success', 'message' => $this->onboarding->is_completed
            ? 'Onboarding als fertig markiert.'
            : 'Onboarding als nicht fertig markiert.']);
    }

    public function generatePortalLink(): void
    {
        $link = $this->onboarding->getOrCreatePublicFormLink();

        // Set all pending contracts to 'sent'
        $this->onboarding->onboardingContracts()
            ->where('status', 'pending')
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        $this->portalLinkUrl = route('hcm.public.onboarding-portal', ['token' => $link->token]);
        $this->onboarding->load('onboardingContracts.contractTemplate');

        $this->dispatch('portal-link-generated');
    }

    public function sendContract(int $contractId): void
    {
        try {
            CommsLog::log(
                event: 'hcm_send_contract',
                status: 'info',
                summary: "sendContract aufgerufen für Contract #{$contractId}, Onboarding #{$this->onboarding->id}",
                details: ['contractId' => $contractId, 'onboardingId' => $this->onboarding->id, 'step' => 'entry'],
                extra: [
                    'team_id' => $this->onboarding->team_id,
                    'channel_type' => 'whatsapp',
                    'source' => 'hcm_onboarding',
                    'triggered_by_user_id' => auth()->id(),
                ],
            );

            $contract = HcmOnboardingContract::where('id', $contractId)
                ->where('hcm_onboarding_id', $this->onboarding->id)
                ->firstOrFail();

            // Try WhatsApp first, fall back to link generation
            $settings = HcmApplicantSettings::getOrCreateForTeam($this->onboarding->team_id);
            $templateId = $settings->getSetting('onboarding_wa_template_id');
            $accountId = $settings->getSetting('onboarding_wa_account_id');

            // Auto-resolve accountId from template
            if ($templateId && !$accountId) {
                $tmpl = \Platform\Integrations\Models\IntegrationsWhatsAppTemplate::find($templateId);
                $accountId = $tmpl?->whatsapp_account_id;
            }

            CommsLog::log(
                event: 'hcm_send_contract',
                status: 'info',
                summary: "WA Settings: templateId={$templateId}, accountId={$accountId}",
                details: ['templateId' => $templateId, 'accountId' => $accountId, 'step' => 'settings'],
                extra: [
                    'team_id' => $this->onboarding->team_id,
                    'channel_type' => 'whatsapp',
                    'source' => 'hcm_onboarding',
                    'triggered_by_user_id' => auth()->id(),
                ],
            );

            if ($templateId && $accountId) {
                $this->sendPortalViaWhatsApp();
                return;
            }

            // Fallback: generate link
            $link = $contract->getOrCreatePublicFormLink();

            $contract->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->contractLinkUrl = route('hcm.public.contract-signing', ['token' => $link->token]);
            $this->onboarding->load('onboardingContracts.contractTemplate');

            $this->dispatch('contract-link-generated');
        } catch (\Throwable $e) {
            CommsLog::log(
                event: 'hcm_send_contract',
                status: 'error',
                summary: "sendContract FEHLER: {$e->getMessage()}",
                details: ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'step' => 'exception'],
                extra: [
                    'team_id' => $this->onboarding->team_id,
                    'channel_type' => 'whatsapp',
                    'source' => 'hcm_onboarding',
                    'triggered_by_user_id' => auth()->id(),
                ],
            );
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler: ' . $e->getMessage()]);
        }
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
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Kontakt verknüpft.']);
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
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Kontakt erstellt und verknüpft.']);
    }

    public function unlinkContact($contactId): void
    {
        $this->onboarding->crmContactLinks()
            ->where('contact_id', $contactId)
            ->delete();

        $this->onboarding->load('crmContactLinks.contact');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Kontakt-Verknüpfung entfernt.']);
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

    public function sendPortalViaWhatsApp(): void
    {
        $logExtra = [
            'team_id' => $this->onboarding->team_id,
            'channel_type' => 'whatsapp',
            'source' => 'hcm_onboarding',
            'triggered_by_user_id' => auth()->id(),
        ];

        CommsLog::log(
            event: 'hcm_wa_send',
            status: 'info',
            summary: "sendPortalViaWhatsApp gestartet für Onboarding #{$this->onboarding->id}",
            details: ['step' => 'start', 'onboardingId' => $this->onboarding->id],
            extra: $logExtra,
        );

        $settings = HcmApplicantSettings::getOrCreateForTeam($this->onboarding->team_id);
        $templateId = $settings->getSetting('onboarding_wa_template_id');
        $accountId = $settings->getSetting('onboarding_wa_account_id');
        $variableMapping = $settings->getSetting('onboarding_wa_template_variables', []);

        if (!$templateId) {
            CommsLog::log(event: 'hcm_wa_send', status: 'error', summary: "WA Template nicht konfiguriert", details: ['step' => 'settings_missing'], extra: $logExtra);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'WhatsApp-Template nicht konfiguriert. Bitte in den Onboarding-Einstellungen ein Template auswählen.']);
            return;
        }

        $template = \Platform\Integrations\Models\IntegrationsWhatsAppTemplate::find($templateId);
        if (!$template || $template->status !== 'APPROVED') {
            CommsLog::log(event: 'hcm_wa_send', status: 'error', summary: "Template #{$templateId} nicht gefunden oder nicht APPROVED", details: ['step' => 'template_invalid', 'templateId' => $templateId, 'status' => $template?->status], extra: $logExtra);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'WhatsApp Template nicht gefunden oder nicht genehmigt.']);
            return;
        }

        // Auto-resolve accountId from template if not configured
        if (!$accountId && $template->whatsapp_account_id) {
            $accountId = $template->whatsapp_account_id;
        }

        if (!$accountId) {
            CommsLog::log(event: 'hcm_wa_send', status: 'error', summary: "Kein WA Account konfiguriert und Template hat keinen Account", details: ['step' => 'no_account'], extra: $logExtra);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Kein WhatsApp-Account konfiguriert.']);
            return;
        }

        $phoneNumber = $this->findPhoneNumber();
        if (!$phoneNumber) {
            CommsLog::log(event: 'hcm_wa_send', status: 'error', summary: "Keine Telefonnummer für Onboarding #{$this->onboarding->id}", details: ['step' => 'no_phone'], extra: $logExtra);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Kein Kontakt mit Telefonnummer gefunden. Bitte zuerst einen Kontakt mit Telefonnummer verknüpfen.']);
            return;
        }

        $channel = $this->resolveWhatsAppChannel($accountId);
        if (!$channel) {
            CommsLog::log(event: 'hcm_wa_send', status: 'error', summary: "Kein WA-Kanal für Account #{$accountId}", details: ['step' => 'no_channel', 'accountId' => $accountId], extra: $logExtra);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Kein aktiver WhatsApp-Kanal konfiguriert.']);
            return;
        }

        // Ensure portal link exists
        $link = $this->onboarding->getOrCreatePublicFormLink();
        $portalUrl = route('hcm.public.onboarding-portal', ['token' => $link->token]);
        $this->portalLinkUrl = $portalUrl;

        // Resolve template variables
        $primaryContact = $this->onboarding->crmContactLinks->first()?->contact;
        $variableValues = [
            'candidate_name' => $primaryContact?->full_name ?? '',
            'portal_link' => $portalUrl,
            'job_title' => $this->onboarding->jobTitle?->name ?? $this->onboarding->source_position_title ?? '',
        ];

        // Build body parameters from template
        $bodyParams = $this->parseTemplateBodyParams($template->components ?? []);
        $components = [];

        if (!empty($bodyParams)) {
            // Auto-map if no mapping configured: first param → candidate_name, second → portal_link, third → job_title
            $autoMapDefaults = ['candidate_name', 'portal_link', 'job_title'];

            $bodyParameters = [];
            foreach ($bodyParams as $i => $param) {
                $source = $variableMapping[$param['name']] ?? null;
                // Fallback: auto-map by position
                if (!$source && isset($autoMapDefaults[$i])) {
                    $source = $autoMapDefaults[$i];
                }
                $text = $source ? ($variableValues[$source] ?? '') : '';
                $paramEntry = [
                    'type' => 'text',
                    'text' => $text,
                ];
                if (!is_numeric($param['name'])) {
                    $paramEntry['parameter_name'] = $param['name'];
                }
                $bodyParameters[] = $paramEntry;
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        // URL button — pass portal token
        $hasUrlButton = false;
        foreach ($template->components ?? [] as $comp) {
            if (($comp['type'] ?? '') === 'BUTTONS') {
                foreach ($comp['buttons'] ?? [] as $btn) {
                    if (($btn['type'] ?? '') === 'URL') {
                        $hasUrlButton = true;
                        break 2;
                    }
                }
            }
        }

        if ($hasUrlButton) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => 0,
                'parameters' => [['type' => 'text', 'text' => $link->token]],
            ];
        }

        try {
            CommsLog::log(
                event: 'hcm_wa_send',
                status: 'info',
                summary: "Sende Template '{$template->name}' an {$phoneNumber->international}",
                details: ['step' => 'sending', 'to' => $phoneNumber->international, 'template' => $template->name, 'components' => $components, 'bodyParams' => $bodyParams ?? []],
                extra: array_merge($logExtra, ['recipient' => $phoneNumber->international]),
            );

            $service = app(WhatsAppMetaService::class);
            $message = $service->sendTemplate(
                channel: $channel,
                to: $phoneNumber->international,
                templateName: $template->name,
                components: $components,
                languageCode: $template->language,
                sender: auth()->user(),
            );

            // Set pending contracts to sent
            $this->onboarding->onboardingContracts()
                ->where('status', 'pending')
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

            // Link thread to onboarding
            $message->load('thread');
            $thread = $message->thread;

            CommsLog::log(
                event: 'hcm_wa_send',
                status: 'info',
                summary: "Thread-Linking: thread_id=" . ($thread?->id ?? 'NULL') . ", message_id=" . ($message->id ?? 'NULL'),
                details: [
                    'step' => 'thread_link',
                    'thread_id' => $thread?->id,
                    'message_id' => $message->id ?? null,
                    'morph_class' => $this->onboarding->getMorphClass(),
                    'onboarding_id' => $this->onboarding->id,
                    'has_addContext' => $thread ? method_exists($thread, 'addContext') : false,
                ],
                extra: $logExtra,
            );

            if ($thread) {
                $thread->addContext($this->onboarding->getMorphClass(), $this->onboarding->id, 'onboarding_portal');

                if (!$thread->context_model) {
                    $thread->updateQuietly([
                        'context_model' => $this->onboarding->getMorphClass(),
                        'context_model_id' => $this->onboarding->id,
                    ]);
                }
            }

            $this->onboarding->load('onboardingContracts.contractTemplate');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Portal-Link erfolgreich per WhatsApp gesendet.']);
        } catch (\Throwable $e) {
            CommsLog::log(
                event: 'hcm_wa_send',
                status: 'error',
                summary: "WA Senden FEHLER: {$e->getMessage()}",
                details: ['step' => 'exception', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()],
                extra: $logExtra,
            );
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Senden: ' . $e->getMessage()]);
        }
    }

    private function findPhoneNumber(): ?\Platform\Crm\Models\CrmPhoneNumber
    {
        $this->onboarding->loadMissing(['crmContactLinks.contact.phoneNumbers']);

        foreach ($this->onboarding->crmContactLinks as $link) {
            $contact = $link->contact;
            if (!$contact) continue;

            $primary = $contact->phoneNumbers
                ->where('is_active', true)
                ->where('is_primary', true)
                ->whereNotNull('international')
                ->first();

            if ($primary) return $primary;

            $fallback = $contact->phoneNumbers
                ->where('is_active', true)
                ->whereNotNull('international')
                ->first();

            if ($fallback) return $fallback;
        }

        return null;
    }

    private function resolveWhatsAppChannel($accountId): ?CommsChannel
    {
        if (!$accountId || !class_exists(\Platform\Integrations\Models\IntegrationsWhatsAppAccount::class)) {
            return null;
        }

        $account = \Platform\Integrations\Models\IntegrationsWhatsAppAccount::find($accountId);
        if (!$account || !$account->active) {
            return null;
        }

        return CommsChannel::where('type', 'whatsapp')
            ->where('is_active', true)
            ->where('sender_identifier', $account->phone_number)
            ->first();
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

    public function openContractFields(int $contractId): void
    {
        $contract = HcmOnboardingContract::where('id', $contractId)
            ->where('hcm_onboarding_id', $this->onboarding->id)
            ->firstOrFail();

        $this->activeContractId = $contractId;
        $this->contractFieldDefinitions = $contract->getExtraFieldsWithLabels();
        $this->contractFieldValues = [];

        foreach ($this->contractFieldDefinitions as $field) {
            $this->contractFieldValues[$field['name']] = $field['value'];
        }

        $this->contractFieldsModalShow = true;
    }

    public function saveContractFields(): void
    {
        $contract = HcmOnboardingContract::where('id', $this->activeContractId)
            ->where('hcm_onboarding_id', $this->onboarding->id)
            ->firstOrFail();

        foreach ($this->contractFieldDefinitions as $field) {
            $value = $this->contractFieldValues[$field['name']] ?? null;
            $contract->setExtraField($field['name'], $value);
        }

        // Re-personalize contract content with updated extra field values
        if ($contract->contractTemplate) {
            $contract->personalized_content = $contract->contractTemplate->personalizeContent(
                $this->onboarding,
                $contract
            );

            // For completed contracts: re-embed §15/§16 data at correct positions
            if ($contract->status === 'completed' && !empty($contract->pre_signing_data)) {
                $contract->personalized_content = HcmOnboardingContract::embedPreSigningData(
                    $contract->personalized_content,
                    $contract->pre_signing_data
                );
            }

            $contract->save();
        }

        $this->closeContractFieldsModal();
        $this->onboarding->load('onboardingContracts.contractTemplate');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Vertragsfelder erfolgreich aktualisiert.']);
    }

    public function closeContractFieldsModal(): void
    {
        $this->contractFieldsModalShow = false;
        $this->activeContractId = null;
        $this->contractFieldDefinitions = [];
        $this->contractFieldValues = [];
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
