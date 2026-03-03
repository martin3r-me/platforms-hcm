<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UpdateOnboardingTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboardings.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/onboardings/{id} - Aktualisiert ein Onboarding. Parameter: onboarding_id (required). Wichtig für AutoPilot: auto_pilot_completed_at setzen wenn AutoPilot abgeschlossen.';
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
                'hcm_job_title_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Stelle (Job Title) zuweisen/ändern. Nutze "hcm.job_titles.GET". Setze 0 um die Verknüpfung zu entfernen.',
                ],
                'source_position_title' => [
                    'type' => 'string',
                    'description' => 'Optional: Quell-Stellenbezeichnung.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Notizen.',
                ],
                'auto_pilot' => [
                    'type' => 'boolean',
                    'description' => 'Optional: AutoPilot aktivieren/deaktivieren.',
                ],
                'auto_pilot_completed_at' => [
                    'type' => 'string',
                    'description' => 'Optional: Zeitpunkt der AutoPilot-Fertigstellung (ISO 8601). Setze leeren String um zu löschen.',
                ],
                'preferred_comms_channel_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Bevorzugter Kommunikationskanal.',
                ],
                'enrichment_status' => [
                    'type' => 'string',
                    'description' => 'Optional: Enrichment-Status aktualisieren.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status.',
                ],
                'owned_by_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Owner des Onboardings.',
                ],
            ],
            'required' => ['onboarding_id'],
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
                'onboarding_id',
                HcmOnboarding::class,
                'NOT_FOUND',
                'Onboarding nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HcmOnboarding $onboarding */
            $onboarding = $found['model'];

            if ((int)$onboarding->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Onboarding.');
            }

            // Validate job title if provided
            if (array_key_exists('hcm_job_title_id', $arguments)) {
                $jtId = $arguments['hcm_job_title_id'];
                if ($jtId === 0 || $jtId === '0' || $jtId === null) {
                    $onboarding->hcm_job_title_id = null;
                } else {
                    $jobTitle = HcmJobTitle::query()
                        ->where('team_id', $teamId)
                        ->find((int)$jtId);
                    if (!$jobTitle) {
                        return ToolResult::error('VALIDATION_ERROR', 'Stelle (hcm_job_title_id) nicht gefunden oder kein Zugriff.');
                    }
                    $onboarding->hcm_job_title_id = $jobTitle->id;
                }
            }

            $fields = [
                'source_position_title',
                'notes',
                'auto_pilot',
                'auto_pilot_completed_at',
                'preferred_comms_channel_id',
                'enrichment_status',
                'is_active',
                'owned_by_user_id',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $onboarding->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            $onboarding->save();

            return ToolResult::success([
                'id' => $onboarding->id,
                'uuid' => $onboarding->uuid,
                'source_position_title' => $onboarding->source_position_title,
                'hcm_job_title_id' => $onboarding->hcm_job_title_id,
                'progress' => $onboarding->progress,
                'enrichment_status' => $onboarding->enrichment_status,
                'auto_pilot' => (bool)$onboarding->auto_pilot,
                'auto_pilot_completed_at' => $onboarding->auto_pilot_completed_at?->toISOString(),
                'is_active' => (bool)$onboarding->is_active,
                'team_id' => $onboarding->team_id,
                'message' => 'Onboarding erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Onboardings: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'onboardings', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
