<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class GetEmployeeTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employee.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/employees/{id} - Ruft einen einzelnen Mitarbeiter ab (inkl. Arbeitgeber, Verträge, CRM-Verknüpfungen). Parameter: employee_id (required), team_id (optional). Hinweis: Für Listen/Filter (z.B. "Stundenlöhner") nutze hcm.contracts.GET bzw. hcm.employees.GET statt viele einzelne hcm.employee.GET Calls.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'employee_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Mitarbeiters (ERFORDERLICH). Nutze "hcm.employees.GET" um IDs zu finden.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'include_contacts' => [
                    'type' => 'boolean',
                    'description' => 'Optional: CRM-Kontaktdaten mitladen. Default: true.',
                    'default' => true,
                ],
                'include_contracts_compensation' => [
                    'type' => 'boolean',
                    'description' => 'Optional: In contracts zusätzlich wage_base_type/hourly_wage/base_salary ausgeben. Default: false. Hinweis: sensible Daten.',
                    'default' => false,
                ],
            ],
            'required' => ['employee_id'],
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

            $employeeId = (int)($arguments['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'employee_id ist erforderlich.');
            }

            $includeContacts = (bool)($arguments['include_contacts'] ?? true);
            $includeContractsComp = (bool)($arguments['include_contracts_compensation'] ?? false);

            $with = [
                'employer',
                'contracts' => fn ($q) => $q->orderBy('start_date', 'desc'),
            ];
            if ($includeContacts) {
                $with[] = 'crmContactLinks.contact';
                $with[] = 'crmContactLinks.contact.emailAddresses';
                $with[] = 'crmContactLinks.contact.phoneNumbers';
                $with[] = 'crmContactLinks.contact.postalAddresses';
            }

            $employee = HcmEmployee::query()
                ->with($with)
                ->where('team_id', $teamId)
                ->find($employeeId);

            if (!$employee) {
                return ToolResult::error('NOT_FOUND', 'Mitarbeiter nicht gefunden (oder kein Zugriff).');
            }

            $contacts = [];
            if ($includeContacts) {
                $contacts = $employee->crmContactLinks->map(function ($link) {
                    $c = $link->contact;
                    return [
                        'contact_id' => $c?->id,
                        'full_name' => $c?->full_name,
                        'display_name' => $c?->display_name,
                        'emails' => $c?->emailAddresses?->map(fn ($e) => [
                            'email' => $e->email_address,
                            'is_primary' => (bool)$e->is_primary,
                        ])->values()->toArray() ?? [],
                        'phones' => $c?->phoneNumbers?->map(fn ($p) => [
                            'number' => $p->international,
                            'is_primary' => (bool)$p->is_primary,
                        ])->values()->toArray() ?? [],
                    ];
                })->filter(fn ($x) => $x['contact_id'])->values()->toArray();
            }

            return ToolResult::success([
                'id' => $employee->id,
                'uuid' => $employee->uuid,
                'employee_number' => $employee->employee_number,
                'employer' => [
                    'id' => $employee->employer?->id,
                    'display_name' => $employee->employer?->display_name,
                    'employer_number' => $employee->employer?->employer_number,
                ],
                'team_id' => $employee->team_id,
                'is_active' => (bool)$employee->is_active,
                'crm_contacts' => $contacts,
                'contracts' => $employee->contracts->map(fn ($c) => [
                    'id' => $c->id,
                    'uuid' => $c->uuid,
                    'start_date' => $c->start_date?->toDateString(),
                    'end_date' => $c->end_date?->toDateString(),
                    'is_active' => (bool)$c->is_active,
                    'employment_status' => $c->employment_status,
                    'contract_type' => $c->contract_type,
                    'hours_per_month' => $c->hours_per_month,
                    'hours_per_week_factor' => $c->hours_per_week_factor,
                    'hours_per_week' => $c->hours_per_week,
                    'wage_base_type' => $c->wage_base_type,
                    // Robust: verhindert Undefined-Variable-Warning bei Scope/Refactor
                    'hourly_wage' => (($includeContractsComp ?? false) ? $c->hourly_wage : null),
                    'base_salary' => (($includeContractsComp ?? false) ? $c->base_salary : null),
                    'cost_center' => $c->cost_center,
                ])->values()->toArray(),
                'created_at' => $employee->created_at?->toISOString(),
                'updated_at' => $employee->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Mitarbeiters: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'employee', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


