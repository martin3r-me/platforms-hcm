<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListInterviewsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interviews.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/interviews - Listet Interview-Termine. Parameter: team_id (optional), interview_type_id (optional), hcm_job_title_id (optional), status (optional: planned/confirmed/cancelled/completed), is_active (optional), include_interviewers (optional, bool), include_bookings (optional, bool), filters/search/sort/limit/offset (optional).';
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
                    'interview_type_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Gesprächsart.',
                    ],
                    'hcm_job_title_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Stelle.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (planned/confirmed/cancelled/completed).',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: nur aktive/inaktive Termine.',
                    ],
                    'include_interviewers' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Interviewer mitladen. Default: true.',
                        'default' => true,
                    ],
                    'include_bookings' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Buchungen mitladen. Default: false.',
                        'default' => false,
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

            $includeInterviewers = (bool)($arguments['include_interviewers'] ?? true);
            $includeBookings = (bool)($arguments['include_bookings'] ?? false);

            $with = ['interviewType', 'jobTitle'];
            if ($includeInterviewers) {
                $with[] = 'interviewers';
            }
            if ($includeBookings) {
                $with[] = 'bookings';
                $with[] = 'bookings.onboarding.crmContactLinks.contact';
            }

            $query = HcmInterview::query()
                ->with($with)
                ->withCount('bookings')
                ->where('team_id', $teamId);

            if (isset($arguments['interview_type_id'])) {
                $query->where('interview_type_id', (int)$arguments['interview_type_id']);
            }
            if (isset($arguments['hcm_job_title_id'])) {
                $query->where('hcm_job_title_id', (int)$arguments['hcm_job_title_id']);
            }
            if (isset($arguments['status'])) {
                $query->where('status', (string)$arguments['status']);
            }
            if (isset($arguments['is_active'])) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }

            $this->applyStandardFilters($query, $arguments, ['title', 'status', 'starts_at', 'location', 'is_active', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['title', 'location', 'description']);
            $this->applyStandardSort($query, $arguments, ['starts_at', 'title', 'status', 'created_at'], 'starts_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (HcmInterview $i) use ($includeInterviewers, $includeBookings) {
                $item = [
                    'id' => $i->id,
                    'uuid' => $i->uuid,
                    'title' => $i->title,
                    'description' => $i->description,
                    'interview_type' => $i->interviewType ? [
                        'id' => $i->interviewType->id,
                        'name' => $i->interviewType->name,
                    ] : null,
                    'job_title' => $i->jobTitle ? [
                        'id' => $i->jobTitle->id,
                        'name' => $i->jobTitle->name,
                    ] : null,
                    'location' => $i->location,
                    'starts_at' => $i->starts_at?->toISOString(),
                    'ends_at' => $i->ends_at?->toISOString(),
                    'min_participants' => $i->min_participants,
                    'max_participants' => $i->max_participants,
                    'bookings_count' => $i->bookings_count,
                    'status' => $i->status,
                    'is_active' => (bool)$i->is_active,
                    'created_at' => $i->created_at?->toISOString(),
                ];

                if ($includeInterviewers) {
                    $item['interviewers'] = $i->interviewers->map(fn($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                    ])->values()->toArray();
                }

                if ($includeBookings) {
                    $item['bookings'] = $i->bookings->map(fn($b) => [
                        'id' => $b->id,
                        'onboarding_id' => $b->hcm_onboarding_id,
                        'candidate_name' => $b->onboarding?->crmContactLinks?->first()?->contact?->full_name,
                        'status' => $b->status,
                        'booked_at' => $b->booked_at?->toISOString(),
                    ])->values()->toArray();
                }

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Interviews: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'interviews', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
