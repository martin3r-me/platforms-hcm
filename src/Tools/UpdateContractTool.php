<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationCostCenterLink;

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
                'hours_per_month' => [
                    'type' => 'number',
                    'description' => 'Optional: Monatsstunden. Wenn gesetzt, werden Wochenstunden automatisch berechnet (Monatsstunden / Faktor).',
                ],
                'hours_per_week_factor' => [
                    'type' => 'number',
                    'description' => 'Optional: Faktor für Berechnung Wochenstunden aus Monatsstunden. Default: 4.4. Formel: Wochenstunden = Monatsstunden / Faktor.',
                ],
                'hours_per_week' => [
                    'type' => 'number',
                    'description' => 'Optional: Stunden/Woche. Wird automatisch berechnet, wenn hours_per_month gesetzt ist. Nicht manuell setzen, wenn hours_per_month vorhanden ist.',
                ],
                'wage_base_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Lohngrundart/Vergütungsbasis (z.B. "Stundenlohn" oder "Monatslohn"). Setze auf "" um zu löschen.',
                ],
                'hourly_wage' => [
                    'type' => 'string',
                    'description' => 'Optional: Stundenlohn. Sensible Information. Setze auf "" um zu löschen.',
                ],
                'base_salary' => [
                    'type' => 'string',
                    'description' => 'Optional: Monatslohn/Grundgehalt. Sensible Information. Setze auf "" um zu löschen.',
                ],
                'cost_center_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Kostenstelle (OrganizationCostCenter.id). Nutze organization.cost_centers.GET (IDs nie raten). Setze auf 0/null um zu entfernen.',
                ],
                'cost_center_code' => [
                    'type' => 'string',
                    'description' => 'Optional: Kostenstelle per Code setzen (Alternative zu cost_center_id). Wird gegen organization.cost_centers.GET (Root/Elterteam) aufgelöst.',
                ],
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
            foreach (['contract_type', 'employment_status', 'cost_center', 'is_active', 'wage_base_type'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f] === '' ? null : $arguments[$f];
                }
            }

            // Stunden-Berechnung: Wenn Monatsstunden gesetzt, berechne Wochenstunden automatisch
            $hoursPerMonth = null;
            $hoursPerWeekFactor = null;
            
            if (array_key_exists('hours_per_month', $arguments)) {
                $hoursPerMonth = $arguments['hours_per_month'] === '' || $arguments['hours_per_month'] === null 
                    ? null 
                    : (float)$arguments['hours_per_month'];
                $update['hours_per_month'] = $hoursPerMonth;
            } else {
                // Wenn nicht explizit gesetzt, verwende aktuellen Wert
                $hoursPerMonth = $contract->hours_per_month;
            }
            
            if (array_key_exists('hours_per_week_factor', $arguments)) {
                $hoursPerWeekFactor = $arguments['hours_per_week_factor'] === '' || $arguments['hours_per_week_factor'] === null
                    ? null
                    : (float)$arguments['hours_per_week_factor'];
                $update['hours_per_week_factor'] = $hoursPerWeekFactor;
            } else {
                // Wenn nicht explizit gesetzt, verwende aktuellen Wert oder Default
                $hoursPerWeekFactor = $contract->hours_per_week_factor ?? 4.4;
            }
            
            // Automatische Berechnung der Wochenstunden, wenn Monatsstunden vorhanden
            if ($hoursPerMonth !== null && $hoursPerWeekFactor > 0) {
                $update['hours_per_week'] = round($hoursPerMonth / $hoursPerWeekFactor, 2);
            } elseif (array_key_exists('hours_per_week', $arguments)) {
                // Fallback: Wenn keine Monatsstunden, aber Wochenstunden direkt gesetzt
                $update['hours_per_week'] = $arguments['hours_per_week'] === '' ? null : (float)$arguments['hours_per_week'];
            }

            // Vergütung (verschlüsselt): Zahlen als String speichern, ""/null löschen
            foreach (['hourly_wage', 'base_salary'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $raw = $arguments[$f];
                    if ($raw === null || $raw === '' || $raw === 'null') {
                        $update[$f] = null;
                    } else {
                        $update[$f] = is_numeric($raw) ? (string)$raw : trim((string)$raw);
                    }
                }
            }

            // Kostenstelle (prefer link table, allow resolving from root/parent team)
            $wantsCostCenter = array_key_exists('cost_center_id', $arguments) || array_key_exists('cost_center_code', $arguments);
            if ($wantsCostCenter) {
                $rootTeamId = (int)$resolved['team']->getRootTeam()->id;

                $targetCostCenter = null;
                $rawId = $arguments['cost_center_id'] ?? null;
                $rawCode = $arguments['cost_center_code'] ?? null;

                if ($rawId === 0 || $rawId === '0' || $rawId === null || $rawId === 'null') {
                    // remove link (and also clear legacy columns for safety)
                    OrganizationCostCenterLink::where('linkable_type', HcmEmployeeContract::class)
                        ->where('linkable_id', $contract->id)
                        ->where('is_primary', true)
                        ->delete();
                    $update['cost_center_id'] = null;
                    $update['cost_center'] = null;
                } else {
                    if ($rawId) {
                        $targetCostCenter = OrganizationCostCenter::query()
                            ->where('is_active', true)
                            ->where('id', (int)$rawId)
                            ->first();
                    } elseif (is_string($rawCode) && trim($rawCode) !== '') {
                        $code = trim($rawCode);
                        $targetCostCenter = OrganizationCostCenter::query()
                            ->where('is_active', true)
                            ->where('team_id', $rootTeamId)
                            ->where('code', $code)
                            ->first();
                    }

                    if (!$targetCostCenter) {
                        return ToolResult::error('VALIDATION_ERROR', 'Kostenstelle nicht gefunden. Nutze hcm.lookup.GET lookup="cost_centers" und wähle eine gültige id/code.');
                    }

                    // Allow cost centers from root team or current team (safety)
                    if (!in_array((int)$targetCostCenter->team_id, [$teamId, $rootTeamId], true)) {
                        return ToolResult::error('ACCESS_DENIED', 'Kostenstelle gehört nicht zum aktuellen Team oder Elterteam.');
                    }

                    // Replace current primary link
                    OrganizationCostCenterLink::where('linkable_type', HcmEmployeeContract::class)
                        ->where('linkable_id', $contract->id)
                        ->where('is_primary', true)
                        ->delete();

                    OrganizationCostCenterLink::create([
                        'cost_center_id' => $targetCostCenter->id,
                        'linkable_type' => HcmEmployeeContract::class,
                        'linkable_id' => $contract->id,
                        'start_date' => $contract->start_date?->toDateString(),
                        'end_date' => $contract->end_date?->toDateString(),
                        'is_primary' => true,
                        'team_id' => $teamId,
                        'created_by_user_id' => $context->user?->id,
                    ]);

                    // Keep legacy fields aligned for older UI/exports
                    $update['cost_center_id'] = $targetCostCenter->id;
                    $update['cost_center'] = $targetCostCenter->code;
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
            $contract->loadMissing('costCenterLinks.costCenter');

            $ccObj = $contract->getCostCenter();
            return ToolResult::success([
                'id' => $contract->id,
                'uuid' => $contract->uuid,
                'employee_id' => $contract->employee_id,
                'team_id' => $contract->team_id,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'is_active' => (bool)$contract->is_active,
                'hours_per_month' => $contract->hours_per_month,
                'hours_per_week_factor' => $contract->hours_per_week_factor,
                'hours_per_week' => $contract->hours_per_week,
                'wage_base_type' => $contract->wage_base_type,
                'hourly_wage' => $contract->hourly_wage,
                'base_salary' => $contract->base_salary,
                'cost_center' => $ccObj ? ['id' => $ccObj->id, 'code' => $ccObj->code, 'name' => $ccObj->name] : ($contract->cost_center ? ['id' => null, 'code' => $contract->cost_center, 'name' => null] : null),
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


