<?php

namespace Platform\Hcm\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Crm\Models\CrmContactLink;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class DeleteEmployeeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employees.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /hcm/employees/{id} - Löscht einen Mitarbeiter. Parameter: employee_id (required), confirm (required=true). Hinweis: entfernt auch crm_contact_links auf diesen Employee.';
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
                    'description' => 'ID des Mitarbeiters (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['employee_id', 'confirm'],
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
                'employee_id',
                HcmEmployee::class,
                'NOT_FOUND',
                'Mitarbeiter nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HcmEmployee $employee */
            $employee = $found['model'];

            if ((int)$employee->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Mitarbeiter.');
            }

            $employeeId = (int)$employee->id;
            $employeeNumber = (string)$employee->employee_number;

            DB::transaction(function () use ($employee) {
                // Entferne CRM-Links (kein FK, daher manuell)
                CrmContactLink::query()
                    ->where('linkable_type', HcmEmployee::class)
                    ->where('linkable_id', $employee->id)
                    ->delete();

                // Löschen
                $employee->delete();
            });

            return ToolResult::success([
                'employee_id' => $employeeId,
                'employee_number' => $employeeNumber,
                'message' => 'Mitarbeiter gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Mitarbeiters: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'employees', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}


