<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Überblick über HCM-Konzepte + CRM-Verknüpfung.
 */
class HcmOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'hcm.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/overview - Zeigt Übersicht über HCM-Konzepte (Employee, Applicant, Contract, Employer) und die Verknüpfung Richtung CRM. REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'hcm',
                'scope' => [
                    'team_scoped' => true,
                    'team_id_source' => 'auth()->user()->currentTeam (dynamisch je Modul-Scope) bzw. ToolContext.team',
                ],
                'concepts' => [
                    'employers' => [
                        'model' => 'Platform\\Hcm\\Models\\HcmEmployer',
                        'table' => 'hcm_employers',
                        'key_fields' => ['id', 'uuid', 'employer_number', 'team_id', 'is_active'],
                    ],
                    'employees' => [
                        'model' => 'Platform\\Hcm\\Models\\HcmEmployee',
                        'table' => 'hcm_employees',
                        'key_fields' => ['id', 'uuid', 'employee_number', 'employer_id', 'team_id', 'is_active'],
                        'note' => 'Keine direkte CRM-FK. Verknüpfung läuft über crm_contact_links (polymorph).',
                    ],
                    'contracts' => [
                        'model' => 'Platform\\Hcm\\Models\\HcmEmployeeContract',
                        'table' => 'hcm_employee_contracts',
                        'key_fields' => ['id', 'uuid', 'employee_id', 'start_date', 'end_date', 'team_id', 'is_active'],
                    ],
                    'applicants' => [
                        'model' => 'Platform\\Hcm\\Models\\HcmApplicant',
                        'table' => 'hcm_applicants',
                        'key_fields' => ['id', 'uuid', 'applicant_status_id', 'progress', 'applied_at', 'team_id', 'is_active'],
                        'note' => 'Wie Employees: CRM-Verknüpfung über crm_contact_links (polymorph).',
                    ],
                ],
                'crm_linking' => [
                    'mechanism' => 'crm_contact_links (polymorph: linkable_type/linkable_id)',
                    'employee_linkable_type' => 'Platform\\Hcm\\Models\\HcmEmployee',
                    'applicant_linkable_type' => 'Platform\\Hcm\\Models\\HcmApplicant',
                    'rule' => 'Mitarbeiter sollten (fachlich) mindestens einen CRM Contact haben; technisch ist es aktuell optional (UI bietet Link/Create an).',
                    'where_to_find_in_ui' => 'HCM → Employee → Kontakte (link/create/unlink)',
                ],
                'related_tools' => [
                    'employees' => [
                        'list' => 'hcm.employees.GET',
                        'get' => 'hcm.employee.GET',
                    ],
                    'contracts' => [
                        'list' => 'hcm.contracts.GET',
                    ],
                    'applicants' => [
                        'list' => 'hcm.applicants.GET',
                        'get' => 'hcm.applicant.GET',
                    ],
                    'crm' => [
                        'contacts' => 'crm.contacts.GET',
                        'contact_get' => 'crm.contact.GET (falls vorhanden) / crm.contacts.GET + Filter',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der HCM-Übersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['overview', 'help', 'hcm', 'employees', 'contracts', 'crm'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}


