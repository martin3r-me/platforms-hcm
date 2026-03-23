<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmInterviewType;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class DeleteInterviewTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interview_types.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /hcm/interview-types/{id} - Löscht eine Gesprächsart. Parameter: interview_type_id (required), confirm (required=true). Hinweis: Löscht auch zugehörige Interviews (cascade).';
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
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['interview_type_id', 'confirm'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'interview_type_id', HcmInterviewType::class, 'NOT_FOUND', 'Gesprächsart nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $type = $found['model'];

            if ((int)$type->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Gesprächsart.');
            }

            $typeId = $type->id;
            $typeName = $type->name;
            $type->delete();

            return ToolResult::success([
                'interview_type_id' => $typeId,
                'name' => $typeName,
                'message' => 'Gesprächsart gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Gesprächsart: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'interview_types', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
