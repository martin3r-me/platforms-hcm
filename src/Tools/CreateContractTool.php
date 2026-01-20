<?php

namespace Platform\Hcm\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationCostCenterLink;

class CreateContractTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.contracts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/contracts - Erstellt einen Mitarbeiter-Vertrag. Parameter: employee_id (required), start_date (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'employee_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Mitarbeiters (ERFORDERLICH). Nutze "hcm.employees.GET".',
                ],
                'start_date' => [
                    'type' => 'string',
                    'description' => 'Startdatum (ERFORDERLICH), Format YYYY-MM-DD.',
                ],
                'end_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Enddatum, Format YYYY-MM-DD.',
                ],
                'contract_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Vertragstyp.',
                ],
                'employment_status' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschäftigungsstatus.',
                ],
                'hours_per_month' => [
                    'type' => 'number',
                    'description' => 'Optional: Monatsstunden. Wenn gesetzt, werden Wochenstunden automatisch berechnet (Monatsstunden / Faktor).',
                ],
                'hours_per_week_factor' => [
                    'type' => 'number',
                    'description' => 'Optional: Faktor für Berechnung Wochenstunden aus Monatsstunden. Default: 4.4. Formel: Wochenstunden = Monatsstunden / Faktor.',
                    'default' => 4.4,
                ],
                'hours_per_week' => [
                    'type' => 'number',
                    'description' => 'Optional: Stunden/Woche. Wird automatisch berechnet, wenn hours_per_month gesetzt ist. Nicht manuell setzen, wenn hours_per_month vorhanden ist.',
                ],
                'wage_base_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Lohngrundart/Vergütungsbasis (z.B. "Stundenlohn" oder "Monatslohn").',
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
                    'description' => 'Optional: Kostenstelle (OrganizationCostCenter.id). Nutze organization.cost_centers.GET (IDs nie raten).',
                ],
                'cost_center_code' => [
                    'type' => 'string',
                    'description' => 'Optional: Kostenstelle per Code setzen (Alternative zu cost_center_id). Wird gegen organization.cost_centers.GET (Root/Elterteam) aufgelöst.',
                ],
                'cost_center' => [
                    'type' => 'string',
                    'description' => 'Optional: Kostenstelle (String).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Status. Default true.',
                    'default' => true,
                ],
            ],
            'required' => ['employee_id', 'start_date'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $employeeId = (int)($arguments['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'employee_id ist erforderlich.');
            }

            $employee = HcmEmployee::query()
                ->where('team_id', $teamId)
                ->find($employeeId);
            if (!$employee) {
                return ToolResult::error('EMPLOYEE_NOT_FOUND', 'Mitarbeiter nicht gefunden (oder kein Zugriff).');
            }

            if (empty($arguments['start_date'])) {
                return ToolResult::error('VALIDATION_ERROR', 'start_date ist erforderlich.');
            }

            $start = \Carbon\Carbon::parse($arguments['start_date']);
            $end = null;
            if (!empty($arguments['end_date'])) {
                $end = \Carbon\Carbon::parse($arguments['end_date']);
                if ($end->lt($start)) {
                    return ToolResult::error('VALIDATION_ERROR', 'end_date darf nicht vor start_date liegen.');
                }
            }

            $contract = DB::transaction(function () use ($arguments, $context, $teamId, $employee, $start, $end) {
                $wageBaseType = array_key_exists('wage_base_type', $arguments) ? trim((string)$arguments['wage_base_type']) : null;
                $hourlyWage = null;
                if (array_key_exists('hourly_wage', $arguments)) {
                    $raw = $arguments['hourly_wage'];
                    if ($raw === null || $raw === '' || $raw === 'null') {
                        $hourlyWage = null;
                    } else {
                        $hourlyWage = is_numeric($raw) ? (string)$raw : trim((string)$raw);
                    }
                }
                $baseSalary = null;
                if (array_key_exists('base_salary', $arguments)) {
                    $raw = $arguments['base_salary'];
                    if ($raw === null || $raw === '' || $raw === 'null') {
                        $baseSalary = null;
                    } else {
                        $baseSalary = is_numeric($raw) ? (string)$raw : trim((string)$raw);
                    }
                }

                // Stunden-Berechnung: Wenn Monatsstunden gesetzt, berechne Wochenstunden automatisch
                $hoursPerMonth = isset($arguments['hours_per_month']) && $arguments['hours_per_month'] !== null && $arguments['hours_per_month'] !== '' 
                    ? (float)$arguments['hours_per_month'] 
                    : null;
                $hoursPerWeekFactor = isset($arguments['hours_per_week_factor']) && $arguments['hours_per_week_factor'] !== null && $arguments['hours_per_week_factor'] !== ''
                    ? (float)$arguments['hours_per_week_factor']
                    : 4.4; // Default
                
                $hoursPerWeek = null;
                if ($hoursPerMonth !== null && $hoursPerWeekFactor > 0) {
                    // Automatische Berechnung: Wochenstunden = Monatsstunden / Faktor
                    $hoursPerWeek = round($hoursPerMonth / $hoursPerWeekFactor, 2);
                } elseif (isset($arguments['hours_per_week']) && $arguments['hours_per_week'] !== null && $arguments['hours_per_week'] !== '') {
                    // Fallback: Wenn keine Monatsstunden, aber Wochenstunden direkt gesetzt
                    $hoursPerWeek = (float)$arguments['hours_per_week'];
                }

                $contract = HcmEmployeeContract::create([
                    'employee_id' => $employee->id,
                    'team_id' => $teamId,
                    'created_by_user_id' => $context->user->id,
                    'owned_by_user_id' => $context->user->id,
                    'is_active' => (bool)($arguments['is_active'] ?? true),
                    'start_date' => $start,
                    'end_date' => $end,
                    'contract_type' => $arguments['contract_type'] ?? null,
                    'employment_status' => $arguments['employment_status'] ?? null,
                    'hours_per_month' => $hoursPerMonth,
                    'hours_per_week_factor' => $hoursPerWeekFactor,
                    'hours_per_week' => $hoursPerWeek,
                    'wage_base_type' => $wageBaseType ?: null,
                    'hourly_wage' => $hourlyWage,
                    'base_salary' => $baseSalary,
                    'cost_center' => $arguments['cost_center'] ?? null,
                ]);

                // Kostenstelle (Link-Tabelle, Root/Elterteam)
                $rawId = $arguments['cost_center_id'] ?? null;
                $rawCode = $arguments['cost_center_code'] ?? null;
                if (($rawId && $rawId !== 0 && $rawId !== '0') || (is_string($rawCode) && trim($rawCode) !== '')) {
                    $rootTeamId = (int)\Platform\Core\Models\Team::find($teamId)?->getRootTeam()->id;
                    $targetCostCenter = null;
                    if ($rawId && $rawId !== 0 && $rawId !== '0') {
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
                    if ($targetCostCenter) {
                        if (in_array((int)$targetCostCenter->team_id, [$teamId, $rootTeamId], true)) {
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
                            // legacy alignment
                            $contract->update([
                                'cost_center_id' => $targetCostCenter->id,
                                'cost_center' => $targetCostCenter->code,
                            ]);
                        }
                    }
                }

                return $contract;
            });

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
                'message' => 'Vertrag erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'contracts', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}


