<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListOnboardingsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboardings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/onboardings - Listet Onboardings. Parameter: team_id (optional), is_active (optional), enrichment_status (optional), auto_pilot (optional), hcm_job_title_id (optional), owned_by_user_id (optional), include_contacts (optional, bool), include_job_title (optional, bool), filters/search/sort/limit/offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext. Nutze "core.teams.GET".',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: nur aktive/inaktive Onboardings.',
                    ],
                    'enrichment_status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Enrichment-Status.',
                    ],
                    'auto_pilot' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach AutoPilot aktiv/inaktiv.',
                    ],
                    'hcm_job_title_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Stelle (Job Title ID).',
                    ],
                    'owned_by_user_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Owner (User ID).',
                    ],
                    'is_completed' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach manuell als fertig markiert.',
                    ],
                    'include_contacts' => [
                        'type' => 'boolean',
                        'description' => 'Optional: CRM-Kontaktdaten (über crm_contact_links) mitladen. Default: true.',
                        'default' => true,
                    ],
                    'include_job_title' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Stelle (Job Title) mitladen. Default: false.',
                        'default' => false,
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }

            $teamId = (int)$resolved['team_id'];

            $includeContacts = (bool)($arguments['include_contacts'] ?? true);
            $includeJobTitle = (bool)($arguments['include_job_title'] ?? false);

            $allowedTeamIds = $this->getAllowedTeamIds($teamId);

            $with = ['ownedByUser'];
            if ($includeContacts) {
                $with['crmContactLinks'] = fn ($q) => $q->whereIn('team_id', $allowedTeamIds);
                $with[] = 'crmContactLinks.contact';
                $with[] = 'crmContactLinks.contact.emailAddresses';
                $with[] = 'crmContactLinks.contact.phoneNumbers';
            }
            if ($includeJobTitle) {
                $with[] = 'jobTitle';
            }

            $query = HcmOnboarding::query()
                ->with($with)
                ->forTeam($teamId);

            if (isset($arguments['is_active'])) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }
            if (isset($arguments['enrichment_status'])) {
                $query->where('enrichment_status', (string)$arguments['enrichment_status']);
            }
            if (isset($arguments['auto_pilot'])) {
                $query->where('auto_pilot', (bool)$arguments['auto_pilot']);
            }
            if (isset($arguments['hcm_job_title_id'])) {
                $query->where('hcm_job_title_id', (int)$arguments['hcm_job_title_id']);
            }
            if (isset($arguments['owned_by_user_id'])) {
                $query->where('owned_by_user_id', (int)$arguments['owned_by_user_id']);
            }
            if (isset($arguments['is_completed'])) {
                $query->where('is_completed', (bool)$arguments['is_completed']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'is_active',
                'enrichment_status',
                'auto_pilot',
                'hcm_job_title_id',
                'owned_by_user_id',
                'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['source_position_title', 'notes']);
            $this->applyStandardSort($query, $arguments, [
                'created_at',
                'updated_at',
                'progress',
            ], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (HcmOnboarding $o) use ($includeContacts, $includeJobTitle) {
                $contacts = [];
                if ($includeContacts) {
                    $contacts = $o->crmContactLinks->map(function ($link) {
                        $c = $link->contact;
                        return [
                            'contact_id' => $c?->id,
                            'full_name' => $c?->full_name,
                            'display_name' => $c?->display_name,
                            'email' => $c?->emailAddresses?->first()?->email_address,
                            'phone' => $c?->phoneNumbers?->first()?->international,
                        ];
                    })->filter(fn ($x) => $x['contact_id'])->values()->toArray();
                }

                $item = [
                    'id' => $o->id,
                    'uuid' => $o->uuid,
                    'source_position_title' => $o->source_position_title,
                    'hcm_job_title_id' => $o->hcm_job_title_id,
                    'progress' => $o->progress,
                    'enrichment_status' => $o->enrichment_status,
                    'auto_pilot' => (bool)$o->auto_pilot,
                    'auto_pilot_completed_at' => $o->auto_pilot_completed_at?->toISOString(),
                    'is_active' => (bool)$o->is_active,
                    'is_completed' => (bool)$o->is_completed,
                    'notes' => $o->notes,
                    'owned_by_user_id' => $o->owned_by_user_id,
                    'owned_by_user_name' => $o->ownedByUser?->name,
                    'team_id' => $o->team_id,
                    'crm_contacts' => $contacts,
                    'created_at' => $o->created_at?->toISOString(),
                    'updated_at' => $o->updated_at?->toISOString(),
                ];

                if ($includeJobTitle && $o->jobTitle) {
                    $item['job_title'] = [
                        'id' => $o->jobTitle->id,
                        'name' => $o->jobTitle->name,
                        'code' => $o->jobTitle->code,
                    ];
                }

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Onboardings: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'onboardings', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
