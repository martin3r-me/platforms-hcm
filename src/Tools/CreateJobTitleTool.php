<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateJobTitleTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.job_titles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/job_titles - Erstellt eine Stelle (Job Title). ERFORDERLICH: name.';
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
                    'description' => 'Name der Stelle (ERFORDERLICH). Z.B. "Pflegefachkraft", "Verwaltungsangestellte/r".',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Kürzel/Code der Stelle. Z.B. "PFK", "VA".',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status. Default: true.',
                    'default' => true,
                ],
                'owned_by_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Owner der Stelle. Default: current user.',
                ],
            ],
            'required' => ['name'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $isActive = (bool)($arguments['is_active'] ?? true);
            $ownedByUserId = isset($arguments['owned_by_user_id']) ? (int)$arguments['owned_by_user_id'] : (int)$context->user->id;

            $jobTitle = HcmJobTitle::create([
                'name' => $name,
                'code' => isset($arguments['code']) ? trim((string)$arguments['code']) : null,
                'is_active' => $isActive,
                'team_id' => $teamId,
                'created_by_user_id' => $context->user->id,
                'owned_by_user_id' => $ownedByUserId,
            ]);

            return ToolResult::success([
                'id' => $jobTitle->id,
                'uuid' => $jobTitle->uuid,
                'name' => $jobTitle->name,
                'code' => $jobTitle->code,
                'is_active' => (bool)$jobTitle->is_active,
                'team_id' => $jobTitle->team_id,
                'message' => 'Stelle erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Stelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'job_titles', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
