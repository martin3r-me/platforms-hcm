<?php

namespace Platform\Hcm\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Crm\Models\CrmContactLink;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class DeleteOnboardingTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboardings.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /hcm/onboardings/{id} - Löscht ein Onboarding. Parameter: onboarding_id (required), confirm (required=true). Hinweis: entfernt auch crm_contact_links auf dieses Onboarding.';
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
                    'description' => 'ID des Onboardings (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['onboarding_id', 'confirm'],
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

            $onboardingId = (int)$onboarding->id;

            DB::transaction(function () use ($onboarding) {
                CrmContactLink::query()
                    ->where('linkable_type', HcmOnboarding::class)
                    ->where('linkable_id', $onboarding->id)
                    ->delete();

                $onboarding->delete();
            });

            return ToolResult::success([
                'onboarding_id' => $onboardingId,
                'message' => 'Onboarding gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Onboardings: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'onboardings', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
