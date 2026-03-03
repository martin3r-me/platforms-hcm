<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class DeleteJobTitleTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.job_titles.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /hcm/job_titles/{id} - Löscht eine Stelle (Job Title). Parameter: job_title_id (required), confirm (required=true). Prüft ob noch Onboardings oder Contracts verknüpft sind.';
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
                    'description' => 'ID der Stelle (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['job_title_id', 'confirm'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
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

            // Check for linked onboardings/contracts
            $onboardingsCount = $jobTitle->onboardings()->count();
            $contractsCount = $jobTitle->contracts()->count();

            if ($onboardingsCount > 0 || $contractsCount > 0) {
                return ToolResult::error('HAS_DEPENDENCIES', "Stelle kann nicht gelöscht werden: {$onboardingsCount} Onboarding(s) und {$contractsCount} Contract(s) verknüpft. Entferne zuerst die Verknüpfungen.");
            }

            $jobTitleId = (int)$jobTitle->id;
            $jobTitleName = (string)$jobTitle->name;

            $jobTitle->delete();

            return ToolResult::success([
                'job_title_id' => $jobTitleId,
                'name' => $jobTitleName,
                'message' => 'Stelle gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Stelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'job_titles', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
