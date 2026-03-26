<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmContractTemplate;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UpdateContractTemplateTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.contract_templates.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/contract-templates/{id} - Aktualisiert eine Vertragsvorlage. Parameter: contract_template_id (required).';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Kurzcode.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Optional: Vertragstext.',
                ],
                'requires_signature' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Unterschrift erforderlich?',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierung.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status.',
                ],
            ],
            'required' => ['contract_template_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'contract_template_id', HcmContractTemplate::class, 'NOT_FOUND', 'Vertragsvorlage nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $template = $found['model'];

            if ((int)$template->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Vertragsvorlage.');
            }

            $fields = ['name', 'code', 'description', 'content', 'requires_signature', 'sort_order', 'is_active'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $template->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            $template->save();

            return ToolResult::success([
                'id' => $template->id,
                'uuid' => $template->uuid,
                'name' => $template->name,
                'message' => 'Vertragsvorlage erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Vertragsvorlage: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'contract_templates', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
