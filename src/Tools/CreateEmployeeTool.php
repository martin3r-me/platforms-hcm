<?php

namespace Platform\Hcm\Tools;

use Illuminate\Support\Facades\DB;
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
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateEmployeeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.employees.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/employees - Erstellt einen Mitarbeiter. ERFORDERLICH: employer_id. Zusätzlich MUSS ein CRM-Contact verknüpft werden (entweder contact_id oder create_contact).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'employer_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Arbeitgebers (ERFORDERLICH). Nutze "hcm.employers.GET".',
                ],
                'employee_number' => [
                    'type' => 'string',
                    'description' => 'Optional: Personalnummer. Wenn nicht gesetzt, wird automatisch generiert (pro Employer eindeutig).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status. Default true.',
                    'default' => true,
                ],
                'owned_by_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Owner des Employee-Datensatzes. Default: current user.',
                ],
                'contact_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Existierender CRM Contact, der verknüpft werden soll. MUSS gesetzt sein, wenn create_contact nicht angegeben ist.',
                ],
                'create_contact' => [
                    'type' => 'object',
                    'description' => 'Optional: Erstellt einen neuen CRM Contact und verknüpft ihn. MUSS gesetzt sein, wenn contact_id nicht angegeben ist.',
                    'properties' => [
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string'],
                        'middle_name' => ['type' => 'string'],
                        'nickname' => ['type' => 'string'],
                        'birth_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['first_name', 'last_name'],
                ],
            ],
            'required' => ['employer_id'],
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

            $employerId = (int)($arguments['employer_id'] ?? 0);
            if ($employerId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'employer_id ist erforderlich.');
            }

            $employer = HcmEmployer::query()
                ->where('team_id', $teamId)
                ->find($employerId);
            if (!$employer) {
                return ToolResult::error('EMPLOYER_NOT_FOUND', 'Arbeitgeber nicht gefunden (oder kein Zugriff).');
            }

            $contactId = isset($arguments['contact_id']) ? (int)$arguments['contact_id'] : null;
            $createContact = $arguments['create_contact'] ?? null;

            if (!$contactId && !$createContact) {
                return ToolResult::error('VALIDATION_ERROR', 'Es muss ein CRM Contact verknüpft werden: setze contact_id oder create_contact.');
            }

            $isActive = (bool)($arguments['is_active'] ?? true);
            $ownedByUserId = isset($arguments['owned_by_user_id']) ? (int)$arguments['owned_by_user_id'] : (int)$context->user->id;

            $result = DB::transaction(function () use ($teamId, $context, $employer, $contactId, $createContact, $isActive, $ownedByUserId, $arguments) {
                // Personalnummer: Wenn manuell gesetzt, verwenden; sonst automatisch generieren
                $employeeNumber = isset($arguments['employee_number']) && trim((string)$arguments['employee_number']) !== ''
                    ? trim((string)$arguments['employee_number'])
                    : $employer->generateNextEmployeeNumber();

                $employee = HcmEmployee::create([
                    'employer_id' => $employer->id,
                    'employee_number' => $employeeNumber,
                    'team_id' => $teamId,
                    'created_by_user_id' => $context->user->id,
                    'owned_by_user_id' => $ownedByUserId,
                    'is_active' => $isActive,
                ]);

                $contact = null;
                if ($contactId) {
                    $contact = CrmContact::find($contactId);
                    if (!$contact) {
                        throw new \RuntimeException('CRM Contact nicht gefunden.');
                    }
                    // CRM Policy respektieren (mindestens view)
                    Gate::forUser($context->user)->authorize('view', $contact);

                    // Team-Hierarchie prüfen: Gleiches Team ODER Employee-Team ist Kind des Contact-Teams
                    $contactTeamId = (int)$contact->team_id;
                    $employeeTeamId = (int)$teamId;
                    
                    if ($contactTeamId !== $employeeTeamId) {
                        // Prüfe, ob Employee-Team ein Kind des Contact-Teams ist (Kindteam kann auf Elternteam-Daten zugreifen)
                        $contactTeam = Team::find($contactTeamId);
                        $employeeTeam = Team::find($employeeTeamId);
                        
                        if (!$contactTeam || !$employeeTeam) {
                            throw new \RuntimeException("Team nicht gefunden (Contact: {$contactTeamId}, Employee: {$employeeTeamId}).");
                        }
                        
                        // OK wenn: gleiches Team ODER Employee-Team ist Kind des Contact-Teams
                        if (!$employeeTeam->isChildOf($contactTeam)) {
                            throw new \RuntimeException("CRM Contact gehört nicht zum Team {$teamId} oder einem Elternteam davon.");
                        }
                    }
                } else {
                    Gate::forUser($context->user)->authorize('create', CrmContact::class);
                    $contact = CrmContact::create(array_merge($createContact, [
                        'team_id' => $teamId,
                        'created_by_user_id' => $context->user->id,
                    ]));
                }

                // Link (polymorph via crm_contact_links)
                CrmContactLink::firstOrCreate(
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

                return [$employee, $contact];
            });

            /** @var HcmEmployee $employee */
            /** @var CrmContact $contact */
            [$employee, $contact] = $result;

            return ToolResult::success([
                'id' => $employee->id,
                'uuid' => $employee->uuid,
                'employee_number' => $employee->employee_number,
                'employer_id' => $employee->employer_id,
                'team_id' => $employee->team_id,
                'is_active' => (bool)$employee->is_active,
                'crm_contact' => [
                    'contact_id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'display_name' => $contact->display_name,
                ],
                'message' => 'Mitarbeiter erfolgreich erstellt und mit CRM Contact verknüpft.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den CRM Contact.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Mitarbeiters: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'employees', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}


