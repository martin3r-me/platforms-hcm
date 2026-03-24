<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class GetInterviewTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /hcm/interviews/{id} - Ruft einen einzelnen Interview-Termin ab (inkl. Interviewer, Buchungen, Stelle). Parameter: interview_id (required), team_id (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'interview_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Interviews (ERFORDERLICH). Nutze "hcm.interviews.GET" um IDs zu finden.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
            ],
            'required' => ['interview_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $interviewId = (int)($arguments['interview_id'] ?? 0);
            if ($interviewId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'interview_id ist erforderlich.');
            }

            $interview = HcmInterview::query()
                ->with(['interviewType', 'jobTitle', 'interviewers', 'bookings.onboarding.crmContactLinks.contact'])
                ->where('team_id', $teamId)
                ->find($interviewId);

            if (!$interview) {
                return ToolResult::error('NOT_FOUND', 'Interview nicht gefunden (oder kein Zugriff).');
            }

            return ToolResult::success([
                'id' => $interview->id,
                'uuid' => $interview->uuid,
                'title' => $interview->title,
                'description' => $interview->description,
                'interview_type' => $interview->interviewType ? [
                    'id' => $interview->interviewType->id,
                    'name' => $interview->interviewType->name,
                ] : null,
                'job_title' => $interview->jobTitle ? [
                    'id' => $interview->jobTitle->id,
                    'name' => $interview->jobTitle->name,
                ] : null,
                'location' => $interview->location,
                'starts_at' => $interview->starts_at?->toISOString(),
                'ends_at' => $interview->ends_at?->toISOString(),
                'min_participants' => $interview->min_participants,
                'max_participants' => $interview->max_participants,
                'status' => $interview->status,
                'is_active' => (bool)$interview->is_active,
                'reminder_wa_template_id' => $interview->reminder_wa_template_id,
                'reminder_hours_before' => $interview->reminder_hours_before,
                'interviewers' => $interview->interviewers->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                ])->values()->toArray(),
                'bookings' => $interview->bookings->map(fn($b) => [
                    'id' => $b->id,
                    'uuid' => $b->uuid,
                    'onboarding_id' => $b->hcm_onboarding_id,
                    'candidate_name' => $b->onboarding?->crmContactLinks?->first()?->contact?->full_name,
                    'status' => $b->status,
                    'notes' => $b->notes,
                    'booked_at' => $b->booked_at?->toISOString(),
                ])->values()->toArray(),
                'created_at' => $interview->created_at?->toISOString(),
                'updated_at' => $interview->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Interviews: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['hcm', 'interview', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
