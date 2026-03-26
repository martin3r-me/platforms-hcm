<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmContractTemplate;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class DeleteContractTemplateTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.contract_templates.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /hcm/contract-templates/{id} - Löscht eine Vertragsvorlage (Soft-Delete). Parameter: contract_template_id (required), confirm (required=true). Hinweis: Zugehörige Onboarding-Verträge bleiben bestehen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'contract_template_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Vertragsvorlage (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['contract_template_id', 'confirm'],
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

            if (!($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', 'Bitte bestätige mit confirm: true.');
            }

            $found = $this->validateAndFindModel($arguments, $context, 'contract_template_id', HcmContractTemplate::class, 'NOT_FOUND', 'Vertragsvorlage nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $template = $found['model'];

            if ((int)$template->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Vertragsvorlage.');
            }

            $templateId = $template->id;
            $templateName = $template->name;
            $template->delete();

            return ToolResult::success([
                'contract_template_id' => $templateId,
                'name' => $templateName,
                'message' => 'Vertragsvorlage gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Vertragsvorlage: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'contract_templates', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
