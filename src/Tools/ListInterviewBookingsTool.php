<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Hcm\Models\HcmInterviewBooking;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class ListInterviewBookingsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interview_bookings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/interview-bookings - Listet Interview-Buchungen. Parameter: team_id (optional), interview_id (optional), onboarding_id (optional), status (optional: registered/confirmed/attended/cancelled/no_show), filters/search/sort/limit/offset (optional).';
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
                    'interview_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Interview-Termin.',
                    ],
                    'onboarding_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Onboarding-Kandidat.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (registered/confirmed/attended/cancelled/no_show).',
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

            $query = HcmInterviewBooking::query()
                ->with(['interview.interviewType', 'interview.jobTitle', 'onboarding.crmContactLinks.contact'])
                ->where('team_id', $teamId);

            if (isset($arguments['interview_id'])) {
                $query->where('hcm_interview_id', (int)$arguments['interview_id']);
            }
            if (isset($arguments['onboarding_id'])) {
                $query->where('hcm_onboarding_id', (int)$arguments['onboarding_id']);
            }
            if (isset($arguments['status'])) {
                $query->where('status', (string)$arguments['status']);
            }

            $this->applyStandardFilters($query, $arguments, ['status', 'booked_at', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['notes']);
            $this->applyStandardSort($query, $arguments, ['booked_at', 'status', 'created_at'], 'booked_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn(HcmInterviewBooking $b) => [
                'id' => $b->id,
                'uuid' => $b->uuid,
                'interview' => $b->interview ? [
                    'id' => $b->interview->id,
                    'title' => $b->interview->title,
                    'starts_at' => $b->interview->starts_at?->toISOString(),
                    'interview_type' => $b->interview->interviewType?->name,
                    'job_title' => $b->interview->jobTitle?->name,
                ] : null,
                'onboarding_id' => $b->hcm_onboarding_id,
                'candidate_name' => $b->onboarding?->crmContactLinks?->first()?->contact?->full_name,
                'status' => $b->status,
                'notes' => $b->notes,
                'booked_at' => $b->booked_at?->toISOString(),
                'created_at' => $b->created_at?->toISOString(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Buchungen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'interview_bookings', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
