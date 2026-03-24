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
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class LinkOnboardingContactTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboarding_contacts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/onboardings/{onboarding_id}/contacts - Verknüpft einen bestehenden CRM Contact mit einem Onboarding. Parameter: onboarding_id (required), contact_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'onboarding_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Onboardings (ERFORDERLICH). Nutze "hcm.onboardings.GET".',
                ],
                'contact_id' => [
                    'type' => 'integer',
                    'description' => 'ID des CRM Contacts (ERFORDERLICH). Nutze "crm.contacts.GET".',
                ],
            ],
            'required' => ['onboarding_id', 'contact_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'onboarding_id', HcmOnboarding::class, 'NOT_FOUND', 'Onboarding nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HcmOnboarding $onboarding */
            $onboarding = $found['model'];
            if ((int)$onboarding->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Onboarding.');
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

            // Team-Hierarchie prüfen
            $contactTeamId = (int)$contact->team_id;
            if ($contactTeamId !== $teamId) {
                $contactTeam = Team::find($contactTeamId);
                $employeeTeam = Team::find($teamId);

                if (!$contactTeam || !$employeeTeam) {
                    return ToolResult::error('VALIDATION_ERROR', "Team nicht gefunden (Contact: {$contactTeamId}, Onboarding: {$teamId}).");
                }

                if (!$employeeTeam->isChildOf($contactTeam)) {
                    return ToolResult::error('VALIDATION_ERROR', "CRM Contact gehört nicht zum Team {$teamId} oder einem Elternteam davon.");
                }
            }

            $link = CrmContactLink::firstOrCreate(
                [
                    'contact_id' => $contact->id,
                    'linkable_type' => HcmOnboarding::class,
                    'linkable_id' => $onboarding->id,
                ],
                [
                    'team_id' => $teamId,
                    'created_by_user_id' => $context->user->id,
                ]
            );

            return ToolResult::success([
                'onboarding_id' => $onboarding->id,
                'contact_id' => $contact->id,
                'contact_name' => $contact->full_name,
                'already_linked' => !$link->wasRecentlyCreated,
                'message' => 'CRM Contact mit Onboarding verknüpft.',
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
            'tags' => ['hcm', 'onboarding', 'crm', 'link', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
