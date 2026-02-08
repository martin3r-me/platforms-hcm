<?php

namespace Platform\Hcm\Tools;

use Illuminate\Database\Eloquent\Builder;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmChurchTaxType;
use Platform\Issuance\Models\IssIssueType;
use Platform\Hcm\Models\HcmEmployeeTrainingType;
use Platform\Hcm\Models\HcmEmploymentRelationship;
use Platform\Hcm\Models\HcmHealthInsuranceCompany;
use Platform\Hcm\Models\HcmInsuranceStatus;
use Platform\Hcm\Models\HcmJobActivity;
use Platform\Hcm\Models\HcmJobActivityAlias;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Models\HcmLevyType;
use Platform\Hcm\Models\HcmAbsenceReason;
use Platform\Hcm\Models\HcmApplicantStatus;
use Platform\Hcm\Models\HcmAutoPilotState;
use Platform\Hcm\Models\HcmPayrollProvider;
use Platform\Hcm\Models\HcmPayrollType;
use Platform\Hcm\Models\HcmPayoutMethod;
use Platform\Hcm\Models\HcmPensionType;
use Platform\Hcm\Models\HcmPersonGroup;
use Platform\Hcm\Models\HcmTariffAgreement;
use Platform\Hcm\Models\HcmTariffAgreementVersion;
use Platform\Hcm\Models\HcmTariffGroup;
use Platform\Hcm\Models\HcmTariffLevel;
use Platform\Hcm\Models\HcmTariffRate;
use Platform\Hcm\Models\HcmTaxClass;
use Platform\Hcm\Models\HcmTaxFactor;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

/**
 * Generisches Lookup-GET für HCM, damit der Agent IDs nicht raten muss.
 *
 * Beispiel:
 * - hcm.lookup.GET { "team_id": 1, "lookup": "insurance_statuses", "search": "KV" }
 * - hcm.lookup.GET { "lookup": "tax_classes", "code": "1" }
 */
class GetHcmLookupTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.lookup.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/lookup - Listet Einträge aus einer HCM-Lookup-Tabelle. Nutze hcm.lookups.GET für verfügbare lookup keys. Unterstützt Suche/Filter/Sort/Pagination. Team-Scoping wird konsequent erzwungen.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema([
                'lookup',
                'team_id',
                'is_active',
                'code',
                'tariff_agreement_id',
                'tariff_group_id',
                'tariff_level_id',
                'job_activity_id',
            ]),
            [
                'properties' => [
                    'lookup' => [
                        'type' => 'string',
                        'description' => 'ERFORDERLICH. Lookup-Key. Nutze hcm.lookups.GET um die Keys zu sehen.',
                        'enum' => array_values(self::LOOKUP_KEYS),
                    ],
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Für team-scoped Lookups erforderlich (Default: Team aus Kontext).',
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Optional: Exakter code-Filter.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach is_active (falls Feld vorhanden).',
                    ],
                    'tariff_agreement_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Nur für tariff_* Lookups: Filter nach Tarifvertrag.',
                    ],
                    'tariff_group_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Für tariff_levels / tariff_rates: Filter nach Tarifgruppe.',
                    ],
                    'tariff_level_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Für tariff_rates: Filter nach Tarifstufe.',
                    ],
                    'job_activity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Für job_activity_aliases: Filter nach job_activity_id.',
                    ],
                ],
                'required' => ['lookup'],
            ]
        );
    }

    /**
     * Keep keys in one place so schema + resolver stay in sync.
     */
    private const LOOKUP_KEYS = [
        'insurance_statuses',
        'pension_types',
        'employment_relationships',
        'person_groups',
        'levy_types',
        'absence_reasons',
        'health_insurance_companies',
        'payout_methods',
        'employee_issue_types',
        'employee_training_types',
        'job_titles',
        'job_activities',
        'job_activity_aliases',
        'tax_classes',
        'tax_factors',
        'church_tax_types',
        'payroll_providers',
        'payroll_types',
        'tariff_agreements',
        'tariff_agreement_versions',
        'tariff_groups',
        'tariff_levels',
        'tariff_rates',
        'applicant_statuses',
        'auto_pilot_states',
    ];

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $lookup = (string)($arguments['lookup'] ?? '');
            if ($lookup === '') {
                return ToolResult::error('VALIDATION_ERROR', 'lookup ist erforderlich. Nutze hcm.lookups.GET.');
            }

            $cfg = $this->resolveLookup($lookup);
            if ($cfg === null) {
                return ToolResult::error('VALIDATION_ERROR', 'Unbekannter lookup. Nutze hcm.lookups.GET.');
            }

            $teamId = null;
            if (($cfg['team_scoped'] ?? false) === true) {
                $resolved = $this->resolveTeam($arguments, $context);
                if ($resolved['error']) {
                    return $resolved['error'];
                }
                $teamId = (int)$resolved['team_id'];
            } elseif (isset($arguments['team_id']) && (int)$arguments['team_id'] > 0) {
                // For global lookups: accept team_id but don't require it.
                $teamId = (int)$arguments['team_id'];
            } elseif ($context->team?->id) {
                $teamId = (int)$context->team->id;
            }

            /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
            $modelClass = $cfg['model'];
            $table = (new $modelClass())->getTable();

            /** @var Builder $q */
            $q = $modelClass::query();

            // Optional shared filters
            if (array_key_exists('code', $arguments) && $arguments['code'] !== null && $arguments['code'] !== '') {
                $q->where('code', trim((string)$arguments['code']));
            }

            if (array_key_exists('is_active', $arguments) && $this->modelHasColumn($modelClass, 'is_active')) {
                $q->where('is_active', (bool)$arguments['is_active']);
            }

            // Team scoping
            if (($cfg['scope'] ?? null) === 'team_id') {
                // direct column
                $q->where($table . '.team_id', (int)$teamId);
            } elseif (($cfg['scope'] ?? null) === 'tariff_agreement_team') {
                // via join to hcm_tariff_agreements.team_id
                $q->select($table . '.*')
                    ->join('hcm_tariff_agreements', 'hcm_tariff_agreements.id', '=', $table . '.tariff_agreement_id')
                    ->where('hcm_tariff_agreements.team_id', (int)$teamId);

                if (!empty($arguments['tariff_agreement_id'])) {
                    $q->where($table . '.tariff_agreement_id', (int)$arguments['tariff_agreement_id']);
                }
            } elseif (($cfg['scope'] ?? null) === 'tariff_group_team') {
                // via groups -> agreements
                $q->select($table . '.*')
                    ->join('hcm_tariff_groups', 'hcm_tariff_groups.id', '=', $table . '.tariff_group_id')
                    ->join('hcm_tariff_agreements', 'hcm_tariff_agreements.id', '=', 'hcm_tariff_groups.tariff_agreement_id')
                    ->where('hcm_tariff_agreements.team_id', (int)$teamId);

                if (!empty($arguments['tariff_group_id'])) {
                    $q->where($table . '.tariff_group_id', (int)$arguments['tariff_group_id']);
                }
            } elseif (($cfg['scope'] ?? null) === 'tariff_rate_team') {
                // rates -> groups -> agreements
                $q->select($table . '.*')
                    ->join('hcm_tariff_groups', 'hcm_tariff_groups.id', '=', $table . '.tariff_group_id')
                    ->join('hcm_tariff_agreements', 'hcm_tariff_agreements.id', '=', 'hcm_tariff_groups.tariff_agreement_id')
                    ->where('hcm_tariff_agreements.team_id', (int)$teamId);

                if (!empty($arguments['tariff_group_id'])) {
                    $q->where($table . '.tariff_group_id', (int)$arguments['tariff_group_id']);
                }
                if (!empty($arguments['tariff_level_id'])) {
                    $q->where($table . '.tariff_level_id', (int)$arguments['tariff_level_id']);
                }
            } elseif (($cfg['scope'] ?? null) === 'job_activity_alias_team') {
                $q->where($table . '.team_id', (int)$teamId);
                if (!empty($arguments['job_activity_id'])) {
                    $q->where($table . '.job_activity_id', (int)$arguments['job_activity_id']);
                }
            }

            // Standard ops
            $this->applyStandardFilters($q, $arguments, ['is_active', 'code', 'tariff_agreement_id', 'tariff_group_id', 'tariff_level_id', 'job_activity_id', 'team_id']);
            $this->applyStandardSearch($q, $arguments, $cfg['search_fields']);
            $this->applyStandardSort(
                $q,
                $arguments,
                $cfg['sort_fields'],
                $cfg['default_sort_field'],
                $cfg['default_sort_dir']
            );

            $paginationResult = $this->applyStandardPaginationResult($q, $arguments);
            $items = $paginationResult['data']->map(fn ($m) => $this->formatItem($m, $lookup))->values()->toArray();

            return ToolResult::success([
                'lookup' => $lookup,
                'items' => $items,
                'pagination' => $paginationResult['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Lookups: ' . $e->getMessage());
        }
    }

    private function resolveLookup(string $lookup): ?array
    {
        return match ($lookup) {
            'insurance_statuses' => [
                'model' => HcmInsuranceStatus::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'pension_types' => [
                'model' => HcmPensionType::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'employment_relationships' => [
                'model' => HcmEmploymentRelationship::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'person_groups' => [
                'model' => HcmPersonGroup::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'levy_types' => [
                'model' => HcmLevyType::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'absence_reasons' => [
                'model' => HcmAbsenceReason::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'short_name', 'description', 'category'],
                'sort_fields' => ['sort_order', 'name', 'code', 'created_at'],
                'default_sort_field' => 'sort_order',
                'default_sort_dir' => 'asc',
            ],
            'health_insurance_companies' => [
                'model' => HcmHealthInsuranceCompany::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'short_name', 'code', 'ik_number', 'website'],
                'sort_fields' => ['name', 'code', 'ik_number', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'payout_methods' => [
                'model' => HcmPayoutMethod::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'external_code'],
                'sort_fields' => ['name', 'code', 'external_code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'employee_issue_types' => [
                'model' => IssIssueType::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'category'],
                'sort_fields' => ['name', 'code', 'category', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'employee_training_types' => [
                'model' => HcmEmployeeTrainingType::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'category', 'description'],
                'sort_fields' => ['name', 'code', 'category', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'job_titles' => [
                'model' => HcmJobTitle::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'job_activities' => [
                'model' => HcmJobActivity::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'job_activity_aliases' => [
                'model' => HcmJobActivityAlias::class,
                'team_scoped' => true,
                'scope' => 'job_activity_alias_team',
                'search_fields' => ['alias'],
                'sort_fields' => ['alias', 'created_at'],
                'default_sort_field' => 'alias',
                'default_sort_dir' => 'asc',
            ],
            'tax_classes' => [
                'model' => HcmTaxClass::class,
                'team_scoped' => false,
                'scope' => null,
                'search_fields' => ['name', 'code'],
                'sort_fields' => ['name', 'code', 'id'],
                'default_sort_field' => 'code',
                'default_sort_dir' => 'asc',
            ],
            'tax_factors' => [
                'model' => HcmTaxFactor::class,
                'team_scoped' => false,
                'scope' => null,
                'search_fields' => ['name', 'code', 'value'],
                'sort_fields' => ['value', 'code', 'id'],
                'default_sort_field' => 'value',
                'default_sort_dir' => 'asc',
            ],
            'church_tax_types' => [
                'model' => HcmChurchTaxType::class,
                'team_scoped' => false,
                'scope' => null,
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'id'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'payroll_providers' => [
                'model' => HcmPayrollProvider::class,
                'team_scoped' => false,
                'scope' => null,
                'search_fields' => ['name', 'key'],
                'sort_fields' => ['name', 'key', 'id'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'payroll_types' => [
                'model' => HcmPayrollType::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'short_name', 'code', 'lanr', 'typ', 'category'],
                'sort_fields' => ['sort_order', 'name', 'code', 'lanr', 'valid_from', 'valid_to', 'created_at'],
                'default_sort_field' => 'sort_order',
                'default_sort_dir' => 'asc',
            ],
            'tariff_agreements' => [
                'model' => HcmTariffAgreement::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'tariff_agreement_versions' => [
                'model' => HcmTariffAgreementVersion::class,
                'team_scoped' => true,
                'scope' => 'tariff_agreement_team',
                'search_fields' => ['status', 'notes'],
                'sort_fields' => ['effective_from', 'status', 'id'],
                'default_sort_field' => 'effective_from',
                'default_sort_dir' => 'desc',
            ],
            'tariff_groups' => [
                'model' => HcmTariffGroup::class,
                'team_scoped' => true,
                'scope' => 'tariff_agreement_team',
                'search_fields' => ['name', 'code'],
                'sort_fields' => ['code', 'name', 'id'],
                'default_sort_field' => 'code',
                'default_sort_dir' => 'asc',
            ],
            'tariff_levels' => [
                'model' => HcmTariffLevel::class,
                'team_scoped' => true,
                'scope' => 'tariff_group_team',
                'search_fields' => ['name', 'code'],
                'sort_fields' => ['code', 'name', 'id'],
                'default_sort_field' => 'code',
                'default_sort_dir' => 'asc',
            ],
            'tariff_rates' => [
                'model' => HcmTariffRate::class,
                'team_scoped' => true,
                'scope' => 'tariff_rate_team',
                'search_fields' => ['amount'],
                'sort_fields' => ['valid_from', 'amount', 'id'],
                'default_sort_field' => 'valid_from',
                'default_sort_dir' => 'desc',
            ],
            'applicant_statuses' => [
                'model' => HcmApplicantStatus::class,
                'team_scoped' => true,
                'scope' => 'team_id',
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            'auto_pilot_states' => [
                'model' => HcmAutoPilotState::class,
                'team_scoped' => false,
                'scope' => null,
                'search_fields' => ['name', 'code', 'description'],
                'sort_fields' => ['name', 'code', 'created_at'],
                'default_sort_field' => 'name',
                'default_sort_dir' => 'asc',
            ],
            default => null,
        };
    }

    private function formatItem(object $m, string $lookup): array
    {
        $base = [
            'id' => $m->id ?? null,
            'name' => $m->name ?? ($m->alias ?? null),
            'code' => $m->code ?? null,
            'is_active' => property_exists($m, 'is_active') ? (bool)($m->is_active ?? true) : true,
        ];

        return match ($lookup) {
            'health_insurance_companies' => $base + [
                'ik_number' => $m->ik_number ?? null,
                'short_name' => $m->short_name ?? null,
                'website' => $m->website ?? null,
            ],
            'payout_methods' => $base + [
                'external_code' => $m->external_code ?? null,
            ],
            'employee_issue_types' => $base + [
                'category' => $m->category ?? null,
                'requires_return' => property_exists($m, 'requires_return') ? (bool)($m->requires_return ?? false) : null,
            ],
            'employee_training_types' => $base + [
                'category' => $m->category ?? null,
                'requires_certification' => property_exists($m, 'requires_certification') ? (bool)($m->requires_certification ?? false) : null,
                'validity_months' => $m->validity_months ?? null,
                'is_mandatory' => property_exists($m, 'is_mandatory') ? (bool)($m->is_mandatory ?? false) : null,
            ],
            'job_activity_aliases' => [
                'id' => $m->id ?? null,
                'job_activity_id' => $m->job_activity_id ?? null,
                'alias' => $m->alias ?? null,
                'team_id' => $m->team_id ?? null,
            ],
            'tax_factors' => $base + [
                'value' => $m->value ?? null,
            ],
            'absence_reasons' => $base + [
                'short_name' => $m->short_name ?? null,
                'description' => $m->description ?? null,
                'category' => $m->category ?? null,
                'requires_sick_note' => property_exists($m, 'requires_sick_note') ? (bool)($m->requires_sick_note ?? false) : null,
                'is_paid' => property_exists($m, 'is_paid') ? (bool)($m->is_paid ?? true) : null,
                'sort_order' => $m->sort_order ?? null,
            ],
            'payroll_providers' => [
                'id' => $m->id ?? null,
                'key' => $m->key ?? null,
                'name' => $m->name ?? null,
            ],
            'payroll_types' => $base + [
                'lanr' => $m->lanr ?? null,
                'short_name' => $m->short_name ?? null,
                'typ' => $m->typ ?? null,
                'category' => $m->category ?? null,
                'display_group' => $m->display_group ?? null,
                'sort_order' => $m->sort_order ?? null,
                'valid_from' => $m->valid_from?->toDateString() ?? null,
                'valid_to' => $m->valid_to?->toDateString() ?? null,
            ],
            'tariff_agreement_versions' => [
                'id' => $m->id ?? null,
                'uuid' => $m->uuid ?? null,
                'tariff_agreement_id' => $m->tariff_agreement_id ?? null,
                'effective_from' => $m->effective_from?->toDateString() ?? null,
                'status' => $m->status ?? null,
                'notes' => $m->notes ?? null,
            ],
            'tariff_groups' => [
                'id' => $m->id ?? null,
                'tariff_agreement_id' => $m->tariff_agreement_id ?? null,
                'code' => $m->code ?? null,
                'name' => $m->name ?? null,
            ],
            'tariff_levels' => [
                'id' => $m->id ?? null,
                'tariff_group_id' => $m->tariff_group_id ?? null,
                'code' => $m->code ?? null,
                'name' => $m->name ?? null,
                'progression_months' => $m->progression_months ?? null,
            ],
            'tariff_rates' => [
                'id' => $m->id ?? null,
                'tariff_group_id' => $m->tariff_group_id ?? null,
                'tariff_level_id' => $m->tariff_level_id ?? null,
                'amount' => $m->amount ?? null,
                'valid_from' => $m->valid_from?->toDateString() ?? null,
                'valid_to' => $m->valid_to?->toDateString() ?? null,
            ],
            default => $base,
        };
    }

    private function modelHasColumn(string $modelClass, string $column): bool
    {
        try {
            $table = (new $modelClass())->getTable();
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['hcm', 'lookup', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}


