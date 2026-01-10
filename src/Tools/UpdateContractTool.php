<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UpdateContractTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.contracts.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/contracts/{id} - Aktualisiert einen Mitarbeiter-Vertrag. Parameter: contract_id (required).';
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
                    'description' => 'ID des Vertrags (ERFORDERLICH). Nutze "hcm.contracts.GET".',
                ],
                'start_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Startdatum (YYYY-MM-DD).',
                ],
                'end_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Enddatum (YYYY-MM-DD). Setze auf "" um zu löschen.',
                ],
                'contract_type' => ['type' => 'string'],
                'employment_status' => ['type' => 'string'],
                'hours_per_week' => ['type' => 'number'],
                'cost_center' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
            ],
            'required' => ['contract_id'],
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
                'contract_id',
                HcmEmployeeContract::class,
                'NOT_FOUND',
                'Vertrag nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HcmEmployeeContract $contract */
            $contract = $found['model'];

            if ((int)$contract->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Vertrag.');
            }

            $update = [];
            foreach (['contract_type', 'employment_status', 'cost_center', 'is_active', 'hours_per_week'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f] === '' ? null : $arguments[$f];
                }
            }

            if (array_key_exists('start_date', $arguments)) {
                $update['start_date'] = empty($arguments['start_date']) ? null : \Carbon\Carbon::parse($arguments['start_date']);
            }
            if (array_key_exists('end_date', $arguments)) {
                $update['end_date'] = empty($arguments['end_date']) ? null : \Carbon\Carbon::parse($arguments['end_date']);
            }

            // Konsistenz: end >= start
            $finalStart = $update['start_date'] ?? $contract->start_date;
            $finalEnd = array_key_exists('end_date', $update) ? $update['end_date'] : $contract->end_date;
            if ($finalStart && $finalEnd && $finalEnd->lt($finalStart)) {
                return ToolResult::error('VALIDATION_ERROR', 'end_date darf nicht vor start_date liegen.');
            }

            if (!empty($update)) {
                $contract->update($update);
            }
            $contract->refresh();

            return ToolResult::success([
                'id' => $contract->id,
                'uuid' => $contract->uuid,
                'employee_id' => $contract->employee_id,
                'team_id' => $contract->team_id,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'is_active' => (bool)$contract->is_active,
                'message' => 'Vertrag erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'contracts', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


