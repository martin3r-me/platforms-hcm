<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class GetEmployerTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employer.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/employers/{id} - Ruft einen einzelnen Arbeitgeber ab. Parameter: employer_id (required), team_id (optional), include_employees (optional, bool).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'employer_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Arbeitgebers (ERFORDERLICH). Nutze "hcm.employers.GET" um IDs zu finden.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'include_employees' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Mitarbeiter-Summary mitladen. Default: true.',
                    'default' => true,
                ],
            ],
            'required' => ['employer_id'],
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

            $employerId = (int)($arguments['employer_id'] ?? 0);
            if ($employerId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'employer_id ist erforderlich.');
            }

            $includeEmployees = (bool)($arguments['include_employees'] ?? true);

            $employer = HcmEmployer::query()
                ->where('team_id', $teamId)
                ->find($employerId);

            if (!$employer) {
                return ToolResult::error('NOT_FOUND', 'Arbeitgeber nicht gefunden (oder kein Zugriff).');
            }

            $employees = null;
            if ($includeEmployees) {
                $employees = $employer->employees()
                    ->orderBy('employee_number')
                    ->limit(200)
                    ->get()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'employee_number' => $e->employee_number,
                        'is_active' => (bool)$e->is_active,
                        'crm_contact_id' => $e->crmContactLinks()->first()?->contact_id,
                    ])->values()->toArray();
            }

            return ToolResult::success([
                'id' => $employer->id,
                'uuid' => $employer->uuid,
                'employer_number' => $employer->employer_number,
                'display_name' => $employer->display_name,
                'employee_number_prefix' => $employer->employee_number_prefix,
                'employee_number_next' => $employer->employee_number_next,
                'team_id' => (int)$employer->team_id,
                'is_active' => (bool)$employer->is_active,
                'employees' => $employees,
                'created_at' => $employer->created_at?->toISOString(),
                'updated_at' => $employer->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Arbeitgebers: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'employer', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


