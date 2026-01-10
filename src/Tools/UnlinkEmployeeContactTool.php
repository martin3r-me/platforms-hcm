<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Crm\Models\CrmContactLink;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UnlinkEmployeeContactTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employee_contacts.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /hcm/employees/{employee_id}/contacts/{contact_id} - Entfernt die Verknüpfung zwischen Mitarbeiter und CRM Contact. Parameter: employee_id (required), contact_id (required), confirm (optional).';
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
                'contact_id' => [
                    'type' => 'integer',
                    'description' => 'ID des CRM Contacts (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bestätigung. Wenn dadurch kein Contact mehr übrig bleibt, solltest du confirm=true setzen.',
                ],
            ],
            'required' => ['employee_id', 'contact_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'employee_id', HcmEmployee::class, 'NOT_FOUND', 'Mitarbeiter nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HcmEmployee $employee */
            $employee = $found['model'];
            if ((int)$employee->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Mitarbeiter.');
            }

            $contactId = (int)($arguments['contact_id'] ?? 0);
            if ($contactId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'contact_id ist erforderlich.');
            }

            $linksQuery = CrmContactLink::query()
                ->where('linkable_type', HcmEmployee::class)
                ->where('linkable_id', $employee->id)
                ->where('contact_id', $contactId);

            $existing = $linksQuery->first();
            if (!$existing) {
                return ToolResult::success([
                    'employee_id' => $employee->id,
                    'contact_id' => $contactId,
                    'removed' => false,
                    'message' => 'Verknüpfung existiert nicht (nichts zu tun).',
                ]);
            }

            // Warnung wenn das die letzte Contact-Verknüpfung wäre
            $remainingCount = CrmContactLink::query()
                ->where('linkable_type', HcmEmployee::class)
                ->where('linkable_id', $employee->id)
                ->count();

            if ($remainingCount <= 1 && !($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', 'Das ist die letzte CRM-Contact-Verknüpfung dieses Mitarbeiters. Bitte bestätige mit confirm: true.');
            }

            $existing->delete();

            return ToolResult::success([
                'employee_id' => $employee->id,
                'contact_id' => $contactId,
                'removed' => true,
                'message' => 'Verknüpfung entfernt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entfernen der Verknüpfung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'employee', 'crm', 'link', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


