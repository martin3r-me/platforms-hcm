<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmOnboardingContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListOnboardingContractsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboarding_contracts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/onboarding-contracts - Listet Onboarding-Verträge. Parameter: team_id (optional), onboarding_id (optional), contract_template_id (optional), status (optional: pending/sent/in_progress/completed/needs_review), filters/search/sort/limit/offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                    'onboarding_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Onboarding.',
                    ],
                    'contract_template_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Vertragsvorlage.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (pending/sent/in_progress/completed/needs_review).',
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

            $query = HcmOnboardingContract::query()
                ->with(['contractTemplate', 'onboarding.crmContactLinks.contact'])
                ->where('team_id', $teamId);

            if (isset($arguments['onboarding_id'])) {
                $query->where('hcm_onboarding_id', (int)$arguments['onboarding_id']);
            }
            if (isset($arguments['contract_template_id'])) {
                $query->where('hcm_contract_template_id', (int)$arguments['contract_template_id']);
            }
            if (isset($arguments['status'])) {
                $query->where('status', (string)$arguments['status']);
            }

            $this->applyStandardFilters($query, $arguments, ['status', 'sent_at', 'completed_at', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['notes']);
            $this->applyStandardSort($query, $arguments, ['status', 'sent_at', 'completed_at', 'created_at'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn(HcmOnboardingContract $c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'onboarding_id' => $c->hcm_onboarding_id,
                'candidate_name' => $c->onboarding?->crmContactLinks?->first()?->contact?->full_name,
                'contract_template' => $c->contractTemplate ? [
                    'id' => $c->contractTemplate->id,
                    'name' => $c->contractTemplate->name,
                    'code' => $c->contractTemplate->code,
                ] : null,
                'status' => $c->status,
                'has_signature' => !empty($c->signature_data),
                'signed_at' => $c->signed_at?->toISOString(),
                'sent_at' => $c->sent_at?->toISOString(),
                'completed_at' => $c->completed_at?->toISOString(),
                'notes' => $c->notes,
                'created_at' => $c->created_at?->toISOString(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Onboarding-Verträge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'onboarding_contracts', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
