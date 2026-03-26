<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmContractTemplate;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateContractTemplateTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.contract_templates.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/contract-templates - Erstellt eine Vertragsvorlage. ERFORDERLICH: name. Optional: code, description, content (Vertragstext mit Platzhaltern), requires_signature, sort_order.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name der Vertragsvorlage (ERFORDERLICH). z.B. "Arbeitsvertrag", "Infektionsschutzgesetz".',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Kurzcode (max. 20 Zeichen).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Optional: Vertragstext (HTML/Markdown mit Platzhaltern).',
                ],
                'requires_signature' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Unterschrift erforderlich? Default: true.',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierung. Default: 0.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status. Default: true.',
                ],
            ],
            'required' => ['name'],
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

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $template = HcmContractTemplate::create([
                'name' => $name,
                'code' => isset($arguments['code']) ? trim((string)$arguments['code']) : null,
                'description' => $arguments['description'] ?? null,
                'content' => $arguments['content'] ?? null,
                'requires_signature' => (bool)($arguments['requires_signature'] ?? true),
                'sort_order' => (int)($arguments['sort_order'] ?? 0),
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'team_id' => $teamId,
                'created_by_user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $template->id,
                'uuid' => $template->uuid,
                'name' => $template->name,
                'code' => $template->code,
                'message' => 'Vertragsvorlage erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Vertragsvorlage: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'contract_templates', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
