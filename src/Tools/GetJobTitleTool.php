<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class GetJobTitleTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.job_title.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/job_titles/{id} - Ruft eine einzelne Stelle (Job Title) ab inkl. Anzahl verknüpfter Contracts/Onboardings und Extra-Field-Definitionen. Parameter: job_title_id (required), team_id (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'job_title_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Stelle (ERFORDERLICH). Nutze "hcm.job_titles.GET" um IDs zu finden.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
            ],
            'required' => ['job_title_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $jobTitleId = (int)($arguments['job_title_id'] ?? 0);
            if ($jobTitleId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'job_title_id ist erforderlich.');
            }

            $jobTitle = HcmJobTitle::query()
                ->withCount(['contracts', 'onboardings'])
                ->where('team_id', $teamId)
                ->find($jobTitleId);

            if (!$jobTitle) {
                return ToolResult::error('NOT_FOUND', 'Stelle nicht gefunden (oder kein Zugriff).');
            }

            $extraFieldDefinitions = $jobTitle->getExtraFieldDefinitions()->map(fn ($def) => [
                'id' => $def->id,
                'key' => $def->key,
                'label' => $def->label,
                'field_type' => $def->field_type,
                'is_required' => (bool)$def->is_required,
                'options' => $def->options,
            ])->values()->toArray();

            return ToolResult::success([
                'id' => $jobTitle->id,
                'uuid' => $jobTitle->uuid,
                'name' => $jobTitle->name,
                'code' => $jobTitle->code,
                'is_active' => (bool)$jobTitle->is_active,
                'owned_by_user_id' => $jobTitle->owned_by_user_id,
                'team_id' => $jobTitle->team_id,
                'contracts_count' => (int)$jobTitle->contracts_count,
                'onboardings_count' => (int)$jobTitle->onboardings_count,
                'extra_field_definitions' => $extraFieldDefinitions,
                'created_at' => $jobTitle->created_at?->toISOString(),
                'updated_at' => $jobTitle->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Stelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'job_title', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
