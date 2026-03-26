<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmContractTemplate;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Models\HcmOnboardingContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateOnboardingContractTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboarding_contracts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/onboarding-contracts - Weist einem Onboarding einen Vertrag zu. ERFORDERLICH: onboarding_id, contract_template_id. Platzhalter im Template werden automatisch mit echten Werten ersetzt (field_mappings), außer personalized_content wird explizit gesetzt.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'onboarding_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Onboardings (ERFORDERLICH). Nutze "hcm.onboardings.GET".',
                ],
                'contract_template_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Vertragsvorlage (ERFORDERLICH). Nutze "hcm.contract_templates.GET".',
                ],
                'personalized_content' => [
                    'type' => 'string',
                    'description' => 'Optional: Personalisierter Vertragstext. Wenn nicht gesetzt, wird der Template-Content kopiert.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Notizen zum Vertrag.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Status (pending/sent). Default: pending.',
                ],
            ],
            'required' => ['onboarding_id', 'contract_template_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $onboardingId = (int)($arguments['onboarding_id'] ?? 0);
            $templateId = (int)($arguments['contract_template_id'] ?? 0);

            if ($onboardingId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'onboarding_id ist erforderlich.');
            }
            if ($templateId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'contract_template_id ist erforderlich.');
            }

            $onboarding = HcmOnboarding::where('team_id', $teamId)->find($onboardingId);
            if (!$onboarding) {
                return ToolResult::error('NOT_FOUND', 'Onboarding nicht gefunden.');
            }

            $template = HcmContractTemplate::where('team_id', $teamId)->find($templateId);
            if (!$template) {
                return ToolResult::error('NOT_FOUND', 'Vertragsvorlage nicht gefunden.');
            }

            $personalizedContent = $arguments['personalized_content'] ?? $template->personalizeContent($onboarding);

            $status = $arguments['status'] ?? 'pending';
            $validStatuses = ['pending', 'sent'];
            if (!in_array($status, $validStatuses)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger Status für Erstellung. Erlaubt: ' . implode(', ', $validStatuses));
            }

            $contract = HcmOnboardingContract::create([
                'hcm_onboarding_id' => $onboardingId,
                'hcm_contract_template_id' => $templateId,
                'team_id' => $teamId,
                'status' => $status,
                'personalized_content' => $personalizedContent,
                'notes' => $arguments['notes'] ?? null,
                'sent_at' => $status === 'sent' ? now() : null,
                'created_by_user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $contract->id,
                'uuid' => $contract->uuid,
                'onboarding_id' => $onboardingId,
                'contract_template_id' => $templateId,
                'status' => $contract->status,
                'message' => 'Vertrag erfolgreich zugewiesen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'onboarding_contracts', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
