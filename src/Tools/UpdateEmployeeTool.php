<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UpdateEmployeeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employees.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/employees/{id} - Aktualisiert einen Mitarbeiter. Parameter: employee_id (required). Hinweis: CRM-Contact-Link wird über hcm.employee_contacts.* Tools verwaltet.';
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
                'employer_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: neuer Arbeitgeber (muss im selben Team liegen).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status.',
                ],
                'owned_by_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Owner des Employee-Datensatzes.',
                ],
                // Einige häufige Personalfelder (alles optional)
                'birth_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'gender' => ['type' => 'string'],
                'nationality' => ['type' => 'string'],
                'children_count' => ['type' => 'integer'],
                'tax_id_number' => ['type' => 'string'],
                'bank_account_holder' => ['type' => 'string'],
                'bank_iban' => ['type' => 'string'],
                'bank_swift' => ['type' => 'string'],
                'business_email' => ['type' => 'string'],
                'alias' => ['type' => 'string'],
            ],
            'required' => ['employee_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

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

            // Optional: employer wechseln (muss im Team sein)
            if (isset($arguments['employer_id'])) {
                $newEmployer = HcmEmployer::query()->where('team_id', $teamId)->find((int)$arguments['employer_id']);
                if (!$newEmployer) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger employer_id (nicht gefunden oder kein Zugriff).');
                }
                $employee->employer_id = $newEmployer->id;
            }

            $fields = [
                'is_active',
                'owned_by_user_id',
                'birth_date',
                'gender',
                'nationality',
                'children_count',
                'tax_id_number',
                'bank_account_holder',
                'bank_iban',
                'bank_swift',
                'business_email',
                'alias',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $employee->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            $employee->save();

            return ToolResult::success([
                'id' => $employee->id,
                'uuid' => $employee->uuid,
                'employee_number' => $employee->employee_number,
                'employer_id' => $employee->employer_id,
                'team_id' => $employee->team_id,
                'is_active' => (bool)$employee->is_active,
                'message' => 'Mitarbeiter erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Mitarbeiters: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'employees', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


