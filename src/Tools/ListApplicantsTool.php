<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmApplicant;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListApplicantsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.applicants.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/applicants - Listet Bewerber. Parameter: team_id (optional), is_active (optional), applicant_status_id (optional), include_contacts (optional, bool). Suche über CRM-Contact (last_name, first_name).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext. Nutze "core.teams.GET".',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: nur aktive/inaktive Bewerber.',
                    ],
                    'applicant_status_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Bewerbungsstatus. Nutze "hcm.lookup.GET" mit lookup=applicant_statuses.',
                    ],
                    'include_contacts' => [
                        'type' => 'boolean',
                        'description' => 'Optional: CRM-Kontaktdaten (über crm_contact_links) mitladen. Default: true.',
                        'default' => true,
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

            $includeContacts = (bool)($arguments['include_contacts'] ?? true);

            $with = ['applicantStatus'];
            if ($includeContacts) {
                $with[] = 'crmContactLinks.contact';
                $with[] = 'crmContactLinks.contact.emailAddresses';
                $with[] = 'crmContactLinks.contact.phoneNumbers';
            }

            $query = HcmApplicant::query()
                ->with($with)
                ->forTeam($teamId);

            if (isset($arguments['is_active'])) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }
            if (isset($arguments['applicant_status_id'])) {
                $query->where('applicant_status_id', (int)$arguments['applicant_status_id']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'is_active',
                'applicant_status_id',
                'created_at',
            ]);

            // Suche über CRM-Contact (last_name, first_name)
            if (!empty($arguments['search'])) {
                $search = (string)$arguments['search'];
                $query->where(function ($q) use ($search) {
                    $q->whereHas('crmContactLinks.contact', function ($cq) use ($search) {
                        $cq->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%");
                    });
                });
            }

            $this->applyStandardSort($query, $arguments, [
                'created_at',
                'applied_at',
                'progress',
            ], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (HcmApplicant $a) use ($includeContacts) {
                $contacts = [];
                if ($includeContacts) {
                    $contacts = $a->crmContactLinks->map(function ($link) {
                        $c = $link->contact;
                        return [
                            'contact_id' => $c?->id,
                            'full_name' => $c?->full_name,
                            'display_name' => $c?->display_name,
                            'email' => $c?->emailAddresses?->first()?->email_address,
                            'phone' => $c?->phoneNumbers?->first()?->international,
                        ];
                    })->filter(fn ($x) => $x['contact_id'])->values()->toArray();
                }

                return [
                    'id' => $a->id,
                    'uuid' => $a->uuid,
                    'applicant_status' => $a->applicantStatus ? [
                        'id' => $a->applicantStatus->id,
                        'name' => $a->applicantStatus->name,
                    ] : null,
                    'progress' => $a->progress,
                    'applied_at' => $a->applied_at?->toDateString(),
                    'is_active' => (bool)$a->is_active,
                    'crm_contacts' => $contacts,
                    'notes' => $a->notes ? mb_substr($a->notes, 0, 200) : null,
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Bewerber: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'applicants', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
