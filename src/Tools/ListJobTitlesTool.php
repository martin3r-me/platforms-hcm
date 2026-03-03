<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListJobTitlesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.job_titles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/job_titles - Listet Stellen (Job Titles). Parameter: team_id (optional), is_active (optional), filters/search/sort/limit/offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext. Nutze "core.teams.GET".',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: nur aktive/inaktive Stellen.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }

            $teamId = (int)$resolved['team_id'];

            $query = HcmJobTitle::query()
                ->withCount(['contracts', 'onboardings'])
                ->where('team_id', $teamId);

            if (isset($arguments['is_active'])) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'is_active',
                'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'code']);
            $this->applyStandardSort($query, $arguments, [
                'name',
                'code',
                'created_at',
            ], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (HcmJobTitle $jt) {
                return [
                    'id' => $jt->id,
                    'uuid' => $jt->uuid,
                    'name' => $jt->name,
                    'code' => $jt->code,
                    'is_active' => (bool)$jt->is_active,
                    'owned_by_user_id' => $jt->owned_by_user_id,
                    'team_id' => $jt->team_id,
                    'contracts_count' => (int)$jt->contracts_count,
                    'onboardings_count' => (int)$jt->onboardings_count,
                    'created_at' => $jt->created_at?->toISOString(),
                    'updated_at' => $jt->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Stellen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'job_titles', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
