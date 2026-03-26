<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmOnboardingContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class DeleteOnboardingContractTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboarding_contracts.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /hcm/onboarding-contracts/{id} - Löscht einen Onboarding-Vertrag. Parameter: contract_id (required), confirm (required=true).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'contract_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Onboarding-Vertrags (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['contract_id', 'confirm'],
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

            if (!($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', 'Bitte bestätige mit confirm: true.');
            }

            $found = $this->validateAndFindModel($arguments, $context, 'contract_id', HcmOnboardingContract::class, 'NOT_FOUND', 'Onboarding-Vertrag nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $contract = $found['model'];

            if ((int)$contract->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diesen Vertrag.');
            }

            $contractId = $contract->id;
            $contract->delete();

            return ToolResult::success([
                'contract_id' => $contractId,
                'message' => 'Onboarding-Vertrag gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'onboarding_contracts', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
