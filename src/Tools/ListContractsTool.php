<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListContractsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.contracts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/contracts - Listet Mitarbeiter-Verträge. Parameter: team_id (optional), employee_id (optional), is_active (optional), include_employee (optional, bool), filters/search/sort/limit/offset (optional).';
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
                    'employee_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Mitarbeiter (hcm_employees.id).',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: nur aktive/inaktive Verträge.',
                    ],
                    'include_employee' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Mitarbeiter (employee_number + CRM-Kontakt-Summary) mitladen. Default: true.',
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

            $includeEmployee = (bool)($arguments['include_employee'] ?? true);

            $with = [];
            if ($includeEmployee) {
                $with[] = 'employee.employer';
                $with[] = 'employee.crmContactLinks.contact';
            }

            $query = HcmEmployeeContract::query()
                ->with($with)
                ->where('team_id', $teamId);

            if (isset($arguments['employee_id'])) {
                $query->where('employee_id', (int)$arguments['employee_id']);
            }
            if (isset($arguments['is_active'])) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'employee_id',
                'start_date',
                'end_date',
                'is_active',
                'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['contract_type', 'employment_status', 'cost_center']);
            $this->applyStandardSort($query, $arguments, [
                'start_date',
                'end_date',
                'created_at',
                'updated_at',
            ], 'start_date', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (HcmEmployeeContract $c) use ($includeEmployee) {
                $employee = null;
                if ($includeEmployee) {
                    $e = $c->employee;
                    $firstContact = $e?->crmContactLinks?->first()?->contact;
                    $employee = $e ? [
                        'id' => $e->id,
                        'employee_number' => $e->employee_number,
                        'employer_id' => $e->employer_id,
                        'employer_name' => $e->employer?->display_name,
                        'crm_contact' => $firstContact ? [
                            'contact_id' => $firstContact->id,
                            'full_name' => $firstContact->full_name,
                            'display_name' => $firstContact->display_name,
                        ] : null,
                    ] : null;
                }

                return [
                    'id' => $c->id,
                    'uuid' => $c->uuid,
                    'employee_id' => $c->employee_id,
                    'team_id' => $c->team_id,
                    'start_date' => $c->start_date?->toDateString(),
                    'end_date' => $c->end_date?->toDateString(),
                    'is_active' => (bool)$c->is_active,
                    'contract_type' => $c->contract_type,
                    'employment_status' => $c->employment_status,
                    'hours_per_week' => $c->hours_per_week,
                    'cost_center' => $c->cost_center,
                    'employee' => $employee,
                    'created_at' => $c->created_at?->toISOString(),
                    'updated_at' => $c->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Verträge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'contracts', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


