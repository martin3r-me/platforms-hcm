<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UpdateJobTitleTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.job_titles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/job_titles/{id} - Aktualisiert eine Stelle (Job Title). Parameter: job_title_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'job_title_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Stelle (ERFORDERLICH). Nutze "hcm.job_titles.GET".',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name der Stelle.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Code/Kürzel.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status.',
                ],
                'owned_by_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Owner der Stelle.',
                ],
            ],
            'required' => ['job_title_id'],
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

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'job_title_id',
                HcmJobTitle::class,
                'NOT_FOUND',
                'Stelle nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HcmJobTitle $jobTitle */
            $jobTitle = $found['model'];

            if ((int)$jobTitle->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Stelle.');
            }

            $fields = [
                'name',
                'code',
                'is_active',
                'owned_by_user_id',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $jobTitle->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            $jobTitle->save();

            return ToolResult::success([
                'id' => $jobTitle->id,
                'uuid' => $jobTitle->uuid,
                'name' => $jobTitle->name,
                'code' => $jobTitle->code,
                'is_active' => (bool)$jobTitle->is_active,
                'team_id' => $jobTitle->team_id,
                'message' => 'Stelle erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Stelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'job_titles', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
