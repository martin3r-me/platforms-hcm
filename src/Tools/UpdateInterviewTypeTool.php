<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmInterviewType;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UpdateInterviewTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interview_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/interview-types/{id} - Aktualisiert eine Gesprächsart. Parameter: interview_type_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'interview_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Gesprächsart (ERFORDERLICH).',
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
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierung.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status.',
                ],
            ],
            'required' => ['interview_type_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'interview_type_id', HcmInterviewType::class, 'NOT_FOUND', 'Gesprächsart nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $type = $found['model'];

            if ((int)$type->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Gesprächsart.');
            }

            $fields = ['name', 'code', 'description', 'sort_order', 'is_active'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $type->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            $type->save();

            return ToolResult::success([
                'id' => $type->id,
                'uuid' => $type->uuid,
                'name' => $type->name,
                'message' => 'Gesprächsart erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Gesprächsart: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'interview_types', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
