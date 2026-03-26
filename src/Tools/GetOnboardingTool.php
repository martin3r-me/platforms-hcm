<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class GetOnboardingTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboarding.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/onboardings/{id} - Ruft ein einzelnes Onboarding ab (inkl. Stelle, CRM-Verknüpfungen, Extra-Fields, Progress). Parameter: onboarding_id (required), team_id (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'onboarding_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Onboardings (ERFORDERLICH). Nutze "hcm.onboardings.GET" um IDs zu finden.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
            ],
            'required' => ['onboarding_id'],
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

            $onboardingId = (int)($arguments['onboarding_id'] ?? 0);
            if ($onboardingId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'onboarding_id ist erforderlich.');
            }

            $allowedTeamIds = $this->getAllowedTeamIds($teamId);

            $with = [
                'jobTitle',
                'ownedByUser',
                'crmContactLinks' => fn ($q) => $q->whereIn('team_id', $allowedTeamIds),
                'crmContactLinks.contact',
                'crmContactLinks.contact.emailAddresses',
                'crmContactLinks.contact.phoneNumbers',
            ];

            $onboarding = HcmOnboarding::query()
                ->with($with)
                ->where('team_id', $teamId)
                ->find($onboardingId);

            if (!$onboarding) {
                return ToolResult::error('NOT_FOUND', 'Onboarding nicht gefunden (oder kein Zugriff).');
            }

            $contacts = $onboarding->crmContactLinks->map(function ($link) {
                $c = $link->contact;
                return [
                    'contact_id' => $c?->id,
                    'full_name' => $c?->full_name,
                    'display_name' => $c?->display_name,
                    'emails' => $c?->emailAddresses?->map(fn ($e) => [
                        'email' => $e->email_address,
                        'is_primary' => (bool)$e->is_primary,
                    ])->values()->toArray() ?? [],
                    'phones' => $c?->phoneNumbers?->map(fn ($p) => [
                        'number' => $p->international,
                        'is_primary' => (bool)$p->is_primary,
                    ])->values()->toArray() ?? [],
                ];
            })->filter(fn ($x) => $x['contact_id'])->values()->toArray();

            $progress = $onboarding->calculateProgress();
            $extraFields = $onboarding->getExtraFieldsWithLabels();

            $data = [
                'id' => $onboarding->id,
                'uuid' => $onboarding->uuid,
                'source_position_title' => $onboarding->source_position_title,
                'hcm_job_title_id' => $onboarding->hcm_job_title_id,
                'job_title' => $onboarding->jobTitle ? [
                    'id' => $onboarding->jobTitle->id,
                    'name' => $onboarding->jobTitle->name,
                    'code' => $onboarding->jobTitle->code,
                ] : null,
                'progress' => $progress,
                'enrichment_status' => $onboarding->enrichment_status,
                'auto_pilot' => (bool)$onboarding->auto_pilot,
                'auto_pilot_completed_at' => $onboarding->auto_pilot_completed_at?->toISOString(),
                'preferred_comms_channel_id' => $onboarding->preferred_comms_channel_id,
                'notes' => $onboarding->notes,
                'is_active' => (bool)$onboarding->is_active,
                'is_completed' => (bool)$onboarding->is_completed,
                'owned_by_user_id' => $onboarding->owned_by_user_id,
                'owned_by_user_name' => $onboarding->ownedByUser?->name,
                'team_id' => $onboarding->team_id,
                'crm_contacts' => $contacts,
                'extra_fields' => $extraFields,
                'created_at' => $onboarding->created_at?->toISOString(),
                'updated_at' => $onboarding->updated_at?->toISOString(),
            ];

            return ToolResult::success($data);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Onboardings: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'onboarding', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
