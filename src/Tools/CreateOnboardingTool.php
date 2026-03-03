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
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateOnboardingTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboardings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/onboardings - Erstellt ein Onboarding. Alle Felder optional. Optional: CRM-Contact verknüpfen (contact_id oder create_contact).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'hcm_job_title_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Verknüpfte Stelle (Job Title). Nutze "hcm.job_titles.GET".',
                ],
                'source_position_title' => [
                    'type' => 'string',
                    'description' => 'Optional: Quell-Stellenbezeichnung (z.B. aus Bewerbung).',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Notizen zum Onboarding.',
                ],
                'auto_pilot' => [
                    'type' => 'boolean',
                    'description' => 'Optional: AutoPilot aktivieren. Default: false.',
                    'default' => false,
                ],
                'preferred_comms_channel_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Bevorzugter Kommunikationskanal (CommsChannel ID).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status. Default: true.',
                    'default' => true,
                ],
                'owned_by_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Owner des Onboardings. Default: current user.',
                ],
                'contact_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Existierender CRM Contact, der verknüpft werden soll.',
                ],
                'create_contact' => [
                    'type' => 'object',
                    'description' => 'Optional: Erstellt einen neuen CRM Contact und verknüpft ihn.',
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

            // Validate job title if provided
            if (isset($arguments['hcm_job_title_id'])) {
                $jobTitle = HcmJobTitle::query()
                    ->where('team_id', $teamId)
                    ->find((int)$arguments['hcm_job_title_id']);
                if (!$jobTitle) {
                    return ToolResult::error('VALIDATION_ERROR', 'Stelle (hcm_job_title_id) nicht gefunden oder kein Zugriff.');
                }
            }

            $contactId = isset($arguments['contact_id']) ? (int)$arguments['contact_id'] : null;
            $createContact = $arguments['create_contact'] ?? null;

            $isActive = (bool)($arguments['is_active'] ?? true);
            $autoPilot = (bool)($arguments['auto_pilot'] ?? false);
            $ownedByUserId = isset($arguments['owned_by_user_id']) ? (int)$arguments['owned_by_user_id'] : (int)$context->user->id;

            $result = DB::transaction(function () use ($teamId, $context, $arguments, $contactId, $createContact, $isActive, $autoPilot, $ownedByUserId) {
                $onboarding = HcmOnboarding::create([
                    'team_id' => $teamId,
                    'hcm_job_title_id' => isset($arguments['hcm_job_title_id']) ? (int)$arguments['hcm_job_title_id'] : null,
                    'source_position_title' => $arguments['source_position_title'] ?? null,
                    'notes' => $arguments['notes'] ?? null,
                    'auto_pilot' => $autoPilot,
                    'preferred_comms_channel_id' => isset($arguments['preferred_comms_channel_id']) ? (int)$arguments['preferred_comms_channel_id'] : null,
                    'is_active' => $isActive,
                    'created_by_user_id' => $context->user->id,
                    'owned_by_user_id' => $ownedByUserId,
                ]);

                $contact = null;
                if ($contactId) {
                    $contact = CrmContact::find($contactId);
                    if (!$contact) {
                        throw new \RuntimeException('CRM Contact nicht gefunden.');
                    }
                    Gate::forUser($context->user)->authorize('view', $contact);

                    $contactTeamId = (int)$contact->team_id;
                    $onboardingTeamId = (int)$teamId;

                    if ($contactTeamId !== $onboardingTeamId) {
                        $contactTeam = Team::find($contactTeamId);
                        $onboardingTeam = Team::find($onboardingTeamId);

                        if (!$contactTeam || !$onboardingTeam) {
                            throw new \RuntimeException("Team nicht gefunden (Contact: {$contactTeamId}, Onboarding: {$onboardingTeamId}).");
                        }

                        if (!$onboardingTeam->isChildOf($contactTeam)) {
                            throw new \RuntimeException("CRM Contact gehört nicht zum Team {$teamId} oder einem Elternteam davon.");
                        }
                    }
                } elseif ($createContact) {
                    Gate::forUser($context->user)->authorize('create', CrmContact::class);
                    $contact = CrmContact::create(array_merge($createContact, [
                        'team_id' => $teamId,
                        'created_by_user_id' => $context->user->id,
                    ]));
                }

                if ($contact) {
                    CrmContactLink::firstOrCreate(
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
                }

                return [$onboarding, $contact];
            });

            /** @var HcmOnboarding $onboarding */
            /** @var CrmContact|null $contact */
            [$onboarding, $contact] = $result;

            $response = [
                'id' => $onboarding->id,
                'uuid' => $onboarding->uuid,
                'source_position_title' => $onboarding->source_position_title,
                'hcm_job_title_id' => $onboarding->hcm_job_title_id,
                'auto_pilot' => (bool)$onboarding->auto_pilot,
                'is_active' => (bool)$onboarding->is_active,
                'team_id' => $onboarding->team_id,
                'message' => 'Onboarding erfolgreich erstellt.',
            ];

            if ($contact) {
                $response['crm_contact'] = [
                    'contact_id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'display_name' => $contact->display_name,
                ];
            }

            return ToolResult::success($response);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den CRM Contact.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Onboardings: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'onboardings', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
