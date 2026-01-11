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
        return 'GET /hcm/contracts - Listet Mitarbeiter-Verträge. Parameter: team_id (optional), employee_id (optional), is_active (optional), wage_base_type (optional), include_employee (optional, bool), filters/search/sort/limit/offset (optional). Tipp: Für "Stundenlöhner" nutze wage_base_type (exakt wie im Vertrag gespeichert; oft "Stundenlohn" oder "Monatslohn") + include_employee=true, damit du NICHT pro Mitarbeiter hcm.employee.GET callen musst. Hinweis: payroll_types (Lohnarten) sind NICHT direkt am Vertrag verknüpft; für Vertragsvergütung nutze wage_base_type.';
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
                    'include_compensation' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Vergütungsfelder (hourly_wage/base_salary) mitladen. Default: true. Hinweis: sensible Daten.',
                        'default' => true,
                    ],
                    'include_cost_center' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Kostenstelle als Objekt (id/code/name) mitladen. Default: true.',
                        'default' => true,
                    ],
                    'wage_base_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Lohngrundart/Vergütungsbasis (exakt wie im Vertrag gespeichert; z.B. "Stundenlohn" oder "Monatslohn").',
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
            $includeComp = (bool)($arguments['include_compensation'] ?? true);
            $includeCostCenter = (bool)($arguments['include_cost_center'] ?? true);

            $with = [];
            if ($includeEmployee) {
                $with[] = 'employee.employer';
                $with[] = 'employee.crmContactLinks.contact';
            }
            if ($includeCostCenter) {
                $with[] = 'costCenterLinks.costCenter';
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
            if (isset($arguments['wage_base_type']) && $arguments['wage_base_type'] !== '') {
                $query->where('wage_base_type', (string)$arguments['wage_base_type']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'employee_id',
                'start_date',
                'end_date',
                'is_active',
                'wage_base_type',
                'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['contract_type', 'employment_status', 'cost_center', 'wage_base_type']);
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
                    'wage_base_type' => $c->wage_base_type,
                    'hourly_wage' => $includeComp ? $c->hourly_wage : null,
                    'base_salary' => $includeComp ? $c->base_salary : null,
                    'cost_center' => $includeCostCenter ? (function () use ($c) {
                        $cc = $c->getCostCenter();
                        if ($cc) {
                            return ['id' => $cc->id, 'code' => $cc->code, 'name' => $cc->name];
                        }
                        return $c->cost_center ? ['id' => null, 'code' => $c->cost_center, 'name' => null] : null;
                    })() : null,
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


