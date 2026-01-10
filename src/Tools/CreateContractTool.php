<?php

namespace Platform\Hcm\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateContractTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.contracts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/contracts - Erstellt einen Mitarbeiter-Vertrag. Parameter: employee_id (required), start_date (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'employee_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Mitarbeiters (ERFORDERLICH). Nutze "hcm.employees.GET".',
                ],
                'start_date' => [
                    'type' => 'string',
                    'description' => 'Startdatum (ERFORDERLICH), Format YYYY-MM-DD.',
                ],
                'end_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Enddatum, Format YYYY-MM-DD.',
                ],
                'contract_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Vertragstyp.',
                ],
                'employment_status' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschäftigungsstatus.',
                ],
                'hours_per_week' => [
                    'type' => 'number',
                    'description' => 'Optional: Stunden/Woche.',
                ],
                'cost_center' => [
                    'type' => 'string',
                    'description' => 'Optional: Kostenstelle (String).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status. Default true.',
                    'default' => true,
                ],
            ],
            'required' => ['employee_id', 'start_date'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $employeeId = (int)($arguments['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'employee_id ist erforderlich.');
            }

            $employee = HcmEmployee::query()
                ->where('team_id', $teamId)
                ->find($employeeId);
            if (!$employee) {
                return ToolResult::error('EMPLOYEE_NOT_FOUND', 'Mitarbeiter nicht gefunden (oder kein Zugriff).');
            }

            if (empty($arguments['start_date'])) {
                return ToolResult::error('VALIDATION_ERROR', 'start_date ist erforderlich.');
            }

            $start = \Carbon\Carbon::parse($arguments['start_date']);
            $end = null;
            if (!empty($arguments['end_date'])) {
                $end = \Carbon\Carbon::parse($arguments['end_date']);
                if ($end->lt($start)) {
                    return ToolResult::error('VALIDATION_ERROR', 'end_date darf nicht vor start_date liegen.');
                }
            }

            $contract = DB::transaction(function () use ($arguments, $context, $teamId, $employee, $start, $end) {
                return HcmEmployeeContract::create([
                    'employee_id' => $employee->id,
                    'team_id' => $teamId,
                    'created_by_user_id' => $context->user->id,
                    'owned_by_user_id' => $context->user->id,
                    'is_active' => (bool)($arguments['is_active'] ?? true),
                    'start_date' => $start,
                    'end_date' => $end,
                    'contract_type' => $arguments['contract_type'] ?? null,
                    'employment_status' => $arguments['employment_status'] ?? null,
                    'hours_per_week' => $arguments['hours_per_week'] ?? null,
                    'cost_center' => $arguments['cost_center'] ?? null,
                ]);
            });

            return ToolResult::success([
                'id' => $contract->id,
                'uuid' => $contract->uuid,
                'employee_id' => $contract->employee_id,
                'team_id' => $contract->team_id,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'is_active' => (bool)$contract->is_active,
                'message' => 'Vertrag erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'contracts', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}


