<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListEmployeesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employees.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/employees - Listet Mitarbeiter. Parameter: team_id (optional), employer_id (optional), is_active (optional), include_contacts (optional, bool), include_contracts (optional, bool), contract_wage_base_type (optional), include_contracts_compensation (optional), filters/search/sort/limit/offset (optional). Hinweis: Für "Stundenlöhner" reicht i.d.R. ein Call: entweder hcm.contracts.GET mit wage_base_type + include_employee=true (empfohlen) oder hcm.employees.GET mit contract_wage_base_type + include_contracts=true.';
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
                    'employer_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Arbeitgeber (hcm_employers.id).',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: nur aktive/inaktive Mitarbeiter.',
                    ],
                    'include_contacts' => [
                        'type' => 'boolean',
                        'description' => 'Optional: CRM-Kontaktdaten (über crm_contact_links) mitladen. Default: true.',
                        'default' => true,
                    ],
                    'include_contracts' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Contracts mitladen. Default: false.',
                        'default' => false,
                    ],
                    'include_contracts_compensation' => [
                        'type' => 'boolean',
                        'description' => 'Optional: In contracts zusätzlich wage_base_type/hourly_wage/base_salary ausgeben. Default: false. Hinweis: sensible Daten.',
                        'default' => false,
                    ],
                    'contract_wage_base_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter Mitarbeiter, die mindestens einen Vertrag mit wage_base_type = diesem Wert haben (exakter Match; z.B. "Stundenlohn" oder "Monatslohn"). Tipp: Alternativ hcm.contracts.GET nutzen.',
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

            $includeContacts = (bool)($arguments['include_contacts'] ?? true);
            $includeContracts = (bool)($arguments['include_contracts'] ?? false);
            $includeContractsComp = (bool)($arguments['include_contracts_compensation'] ?? false);

            $with = ['employer'];
            if ($includeContacts) {
                $with[] = 'crmContactLinks.contact';
                $with[] = 'crmContactLinks.contact.emailAddresses';
                $with[] = 'crmContactLinks.contact.phoneNumbers';
            }
            if ($includeContracts) {
                $with[] = 'contracts';
            }

            $query = HcmEmployee::query()
                ->with($with)
                ->forTeam($teamId);

            if (isset($arguments['employer_id'])) {
                $query->where('employer_id', (int)$arguments['employer_id']);
            }
            if (isset($arguments['is_active'])) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }
            if (isset($arguments['contract_wage_base_type']) && (string)$arguments['contract_wage_base_type'] !== '') {
                $wbt = (string)$arguments['contract_wage_base_type'];
                $query->whereHas('contracts', function ($q) use ($wbt) {
                    $q->where('wage_base_type', $wbt);
                });
            }

            $this->applyStandardFilters($query, $arguments, [
                'employee_number',
                'employer_id',
                'is_active',
                'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['employee_number']);
            $this->applyStandardSort($query, $arguments, [
                'employee_number',
                'created_at',
                'updated_at',
            ], 'employee_number', 'asc');

            // Pagination + result
            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (HcmEmployee $e) use ($includeContacts, $includeContracts, $includeContractsComp) {
                $contacts = [];
                if ($includeContacts) {
                    $contacts = $e->crmContactLinks->map(function ($link) {
                        $c = $link->contact;
                        return [
                            'contact_id' => $c?->id,
                            'full_name' => $c?->full_name,
                            'display_name' => $c?->display_name,
                            'email' => $c?->emailAddresses?->first()?->email_address,
                            'phone' => $c?->phoneNumbers?->first()?->international,
                        ];
                    })->filter(fn ($x) => $x['contact_id'])->values()->toArray();
                }

                $contracts = null;
                if ($includeContracts) {
                    $contracts = $e->contracts->map(fn ($c) => [
                        'id' => $c->id,
                        'start_date' => $c->start_date?->toDateString(),
                        'end_date' => $c->end_date?->toDateString(),
                        'is_active' => (bool)$c->is_active,
                        'hours_per_week' => $c->hours_per_week,
                        'wage_base_type' => $c->wage_base_type,
                        // Robust: falls $includeContractsComp in irgendeinem Scope/Refactor fehlt, verhindert ?? einen Undefined-Variable-Warning
                        'hourly_wage' => (($includeContractsComp ?? false) ? $c->hourly_wage : null),
                        'base_salary' => (($includeContractsComp ?? false) ? $c->base_salary : null),
                        'employment_status' => $c->employment_status,
                    ])->toArray();
                }

                return [
                    'id' => $e->id,
                    'uuid' => $e->uuid,
                    'employee_number' => $e->employee_number,
                    'employer_id' => $e->employer_id,
                    'employer_name' => $e->employer?->display_name,
                    'team_id' => $e->team_id,
                    'is_active' => (bool)$e->is_active,
                    'crm_contacts' => $contacts,
                    'contracts' => $contracts,
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Mitarbeiter: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'employees', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


