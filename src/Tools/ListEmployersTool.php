<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListEmployersTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employers.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/employers - Listet Arbeitgeber. Parameter: team_id (optional), is_active (optional), filters/search/sort/limit/offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: nur aktive/inaktive Arbeitgeber.',
                    ],
                    'include_counts' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Anzahl Mitarbeiter (gesamt/aktiv) mit ausgeben. Default: true.',
                        'default' => true,
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
            $includeCounts = (bool)($arguments['include_counts'] ?? true);

            $query = HcmEmployer::query()->forTeam($teamId);
            if (isset($arguments['is_active'])) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }

            $this->applyStandardFilters($query, $arguments, ['employer_number', 'is_active', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['employer_number']);
            $this->applyStandardSort($query, $arguments, ['employer_number', 'created_at', 'updated_at'], 'employer_number', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (HcmEmployer $e) use ($includeCounts) {
                return [
                    'id' => $e->id,
                    'uuid' => $e->uuid,
                    'employer_number' => $e->employer_number,
                    'display_name' => $e->display_name,
                    'team_id' => (int)$e->team_id,
                    'is_active' => (bool)$e->is_active,
                    'employee_number_prefix' => $e->employee_number_prefix,
                    'employee_number_next' => $e->employee_number_next,
                    'counts' => $includeCounts ? [
                        'employees_total' => $e->employees()->count(),
                        'employees_active' => $e->employees()->where('is_active', true)->count(),
                    ] : null,
                    'created_at' => $e->created_at?->toISOString(),
                    'updated_at' => $e->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Arbeitgeber: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'employers', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


