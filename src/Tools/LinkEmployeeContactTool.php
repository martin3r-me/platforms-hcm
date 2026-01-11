<?php

namespace Platform\Hcm\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Crm\Models\CrmContact;
use Platform\Crm\Models\CrmContactLink;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class LinkEmployeeContactTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employee_contacts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/employees/{employee_id}/contacts - Verknüpft einen bestehenden CRM Contact mit einem Mitarbeiter. Parameter: employee_id (required), contact_id (required).';
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
                'contact_id' => [
                    'type' => 'integer',
                    'description' => 'ID des CRM Contacts (ERFORDERLICH). Nutze "crm.contacts.GET".',
                ],
            ],
            'required' => ['employee_id', 'contact_id'],
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

            $contact = CrmContact::find($contactId);
            if (!$contact) {
                return ToolResult::error('CONTACT_NOT_FOUND', 'CRM Contact nicht gefunden.');
            }
            Gate::forUser($context->user)->authorize('view', $contact);

            // Team-Hierarchie prüfen: Gleiches Team ODER Employee-Team ist Kind des Contact-Teams
            $contactTeamId = (int)$contact->team_id;
            $employeeTeamId = (int)$teamId;
            
            if ($contactTeamId !== $employeeTeamId) {
                // Prüfe, ob Employee-Team ein Kind des Contact-Teams ist (Kindteam kann auf Elternteam-Daten zugreifen)
                $contactTeam = Team::find($contactTeamId);
                $employeeTeam = Team::find($employeeTeamId);
                
                if (!$contactTeam || !$employeeTeam) {
                    return ToolResult::error('VALIDATION_ERROR', "Team nicht gefunden (Contact: {$contactTeamId}, Employee: {$employeeTeamId}).");
                }
                
                // OK wenn: gleiches Team ODER Employee-Team ist Kind des Contact-Teams
                if (!$employeeTeam->isChildOf($contactTeam)) {
                    return ToolResult::error('VALIDATION_ERROR', "CRM Contact gehört nicht zum Team {$teamId} oder einem Elternteam davon.");
                }
            }

            $link = CrmContactLink::firstOrCreate(
                [
                    'contact_id' => $contact->id,
                    'linkable_type' => HcmEmployee::class,
                    'linkable_id' => $employee->id,
                ],
                [
                    'team_id' => $teamId,
                    'created_by_user_id' => $context->user->id,
                ]
            );

            return ToolResult::success([
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'contact_id' => $contact->id,
                'contact_name' => $contact->full_name,
                'already_linked' => !$link->wasRecentlyCreated,
                'message' => 'CRM Contact mit Mitarbeiter verknüpft.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den CRM Contact.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Verknüpfen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'employee', 'crm', 'link', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


