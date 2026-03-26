<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmOnboardingContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class RePersonalizeContractsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboarding_contracts.repersonalize';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/onboarding-contracts/repersonalize - Re-personalisiert den Vertragstext anhand der aktuellen Extra-Field-Werte und Kontaktdaten. Parameter: onboarding_id (optional, alle Verträge dieses Onboardings) ODER contract_id (optional, einzelner Vertrag). Mindestens einer erforderlich.';
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
                    'description' => 'Optional: Onboarding-ID. Alle Verträge dieses Onboardings werden re-personalisiert.',
                ],
                'contract_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Einzelner Vertrag re-personalisieren.',
                ],
            ],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $onboardingId = $arguments['onboarding_id'] ?? null;
            $contractId = $arguments['contract_id'] ?? null;

            if (!$onboardingId && !$contractId) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens onboarding_id oder contract_id ist erforderlich.');
            }

            $query = HcmOnboardingContract::with(['contractTemplate', 'onboarding'])
                ->where('team_id', $teamId);

            if ($contractId) {
                $query->where('id', $contractId);
            } elseif ($onboardingId) {
                $query->where('hcm_onboarding_id', $onboardingId);
            }

            $contracts = $query->get();

            if ($contracts->isEmpty()) {
                return ToolResult::error('NOT_FOUND', 'Keine Verträge gefunden.');
            }

            $results = [];
            foreach ($contracts as $contract) {
                if (!$contract->contractTemplate) {
                    $results[] = [
                        'contract_id' => $contract->id,
                        'status' => 'skipped',
                        'reason' => 'Keine Vertragsvorlage zugeordnet.',
                    ];
                    continue;
                }

                $contract->personalized_content = $contract->contractTemplate->personalizeContent(
                    $contract->onboarding,
                    $contract
                );
                $contract->save();

                $results[] = [
                    'contract_id' => $contract->id,
                    'template' => $contract->contractTemplate->name,
                    'status' => 'updated',
                ];
            }

            return ToolResult::success([
                'updated_count' => collect($results)->where('status', 'updated')->count(),
                'total' => count($results),
                'contracts' => $results,
                'message' => collect($results)->where('status', 'updated')->count() . ' Vertrag/Verträge re-personalisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'onboarding_contracts', 'repersonalize', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
