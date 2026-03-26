<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmOnboardingContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class FillOnboardingContractFieldsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.onboarding_contract_fields.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/onboarding-contracts/{contract_id}/fields - Setzt Extra-Field-Werte auf einem Onboarding-Vertrag (inherited vom Contract-Template). Parameter: contract_id (required), fields (required, Array von {name: string, value: mixed}). Nutze "core.extra_fields.GET" mit model_type="hcm_onboarding_contract" um verfügbare Feldnamen zu sehen.';
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
                'fields' => [
                    'type' => 'array',
                    'description' => 'Array von Feld-Wert-Paaren. Jedes Element: {name: "feldname", value: "wert"}. Nutze den Feldnamen (name), nicht die ID.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Name des Extra-Fields.',
                            ],
                            'value' => [
                                'description' => 'Wert für das Feld. Typ hängt vom Feldtyp ab.',
                            ],
                        ],
                        'required' => ['name', 'value'],
                    ],
                ],
            ],
            'required' => ['contract_id', 'fields'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'contract_id', HcmOnboardingContract::class, 'NOT_FOUND', 'Onboarding-Vertrag nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HcmOnboardingContract $contract */
            $contract = $found['model'];
            if ((int)$contract->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diesen Vertrag.');
            }

            $fields = $arguments['fields'] ?? [];
            if (empty($fields) || !is_array($fields)) {
                return ToolResult::error('VALIDATION_ERROR', 'fields ist erforderlich (Array von {name, value}).');
            }

            $updated = [];
            $errors = [];

            foreach ($fields as $fieldData) {
                $name = $fieldData['name'] ?? null;
                $value = $fieldData['value'] ?? null;

                if (!$name) {
                    $errors[] = 'Feld ohne name übersprungen.';
                    continue;
                }

                try {
                    $contract->setExtraField($name, $value);
                    $updated[] = $name;
                } catch (\Throwable $e) {
                    $errors[] = "Feld '{$name}': {$e->getMessage()}";
                }
            }

            $result = [
                'contract_id' => $contract->id,
                'updated_fields' => $updated,
                'updated_count' => count($updated),
                'message' => count($updated) . ' Feld(er) aktualisiert.',
            ];

            if (!empty($errors)) {
                $result['errors'] = $errors;
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Setzen der Extra-Fields: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'onboarding_contracts', 'extra_fields', 'fill', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
