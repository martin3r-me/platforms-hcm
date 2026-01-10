<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Übersicht über verfügbare Lookup-Tabellen in HCM.
 *
 * Zweck: Agenten sollen Lookup-IDs NICHT raten, sondern deterministisch nachschlagen.
 */
class HcmLookupsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'hcm.lookups.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/lookups - Listet alle HCM-Lookup-Typen (Keys) auf. Nutze danach "hcm.lookup.GET" mit lookup=<typ> um Einträge zu suchen (IDs nie raten).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success([
            'lookups' => [
                [
                    'key' => 'insurance_statuses',
                    'description' => 'Versicherungsstatus (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'pension_types',
                    'description' => 'Rentenarten (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'employment_relationships',
                    'description' => 'Beschäftigungsverhältnisse (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'person_groups',
                    'description' => 'Personengruppen (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'levy_types',
                    'description' => 'Umlagearten (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'health_insurance_companies',
                    'description' => 'Krankenkassen (team-scoped) – code/ik_number/short_name',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'payout_methods',
                    'description' => 'Auszahlungsarten (team-scoped) – external_code',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'employee_issue_types',
                    'description' => 'Mitarbeiter-Vorfalltypen (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'employee_training_types',
                    'description' => 'Schulungstypen (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'job_titles',
                    'description' => 'Job-Titel (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'job_activities',
                    'description' => 'Tätigkeiten/Job-Aktivitäten (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'job_activity_aliases',
                    'description' => 'Alias/Bezeichnungen für Tätigkeiten (team-scoped) – filterbar nach job_activity_id',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'tax_classes',
                    'description' => 'Steuerklassen (global)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'tax_factors',
                    'description' => 'Steuerfaktoren (global) – value',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'church_tax_types',
                    'description' => 'Kirchensteuerarten (global)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'payroll_providers',
                    'description' => 'Payroll Provider (global)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'payroll_types',
                    'description' => 'Lohnarten / Payroll Types (team-scoped) – lanr/typ/category/valid_from/to',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'tariff_agreements',
                    'description' => 'Tarifverträge (team-scoped)',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'tariff_agreement_versions',
                    'description' => 'Tarifvertrag-Versionen (team-scoped via agreement) – filterbar nach tariff_agreement_id',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'tariff_groups',
                    'description' => 'Tarifgruppen (team-scoped via agreement) – filterbar nach tariff_agreement_id',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'tariff_levels',
                    'description' => 'Tarifstufen (team-scoped via agreement) – filterbar nach tariff_group_id',
                    'tool' => 'hcm.lookup.GET',
                ],
                [
                    'key' => 'tariff_rates',
                    'description' => 'Tarifsätze (team-scoped via agreement) – filterbar nach tariff_group_id/tariff_level_id',
                    'tool' => 'hcm.lookup.GET',
                ],
            ],
            'how_to' => [
                'step_1' => 'Nutze hcm.lookups.GET um den passenden lookup-key zu finden.',
                'step_2' => 'Nutze hcm.lookup.GET mit lookup=<key> und search=<text> oder code=<code>.',
                'step_3' => 'Verwende die gefundene id in Write-Tools. Niemals raten.',
            ],
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['hcm', 'lookup', 'help', 'overview'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}


