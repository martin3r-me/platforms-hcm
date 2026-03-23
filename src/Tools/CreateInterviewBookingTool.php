<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Models\HcmInterviewBooking;
use Platform\Hcm\Models\HcmOnboarding;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateInterviewBookingTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interview_bookings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/interview-bookings - Bucht einen Onboarding-Kandidaten für einen Interview-Termin. ERFORDERLICH: interview_id, onboarding_id. Prüft Max-Teilnehmer und Stellen-Zuordnung. Hinweis: Wenn der Termin einer Stelle zugeordnet ist, muss der Kandidat auf derselben Stelle sein.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'interview_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Interview-Termins (ERFORDERLICH). Nutze "hcm.interviews.GET".',
                ],
                'onboarding_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Onboarding-Kandidaten (ERFORDERLICH). Nutze "hcm.onboardings.GET".',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Notizen zur Buchung.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Status (registered/confirmed). Default: registered.',
                ],
            ],
            'required' => ['interview_id', 'onboarding_id'],
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

            $interviewId = (int)($arguments['interview_id'] ?? 0);
            $onboardingId = (int)($arguments['onboarding_id'] ?? 0);

            if ($interviewId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'interview_id ist erforderlich.');
            }
            if ($onboardingId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'onboarding_id ist erforderlich.');
            }

            $interview = HcmInterview::where('team_id', $teamId)->find($interviewId);
            if (!$interview) {
                return ToolResult::error('NOT_FOUND', 'Interview-Termin nicht gefunden.');
            }

            $onboarding = HcmOnboarding::where('team_id', $teamId)->find($onboardingId);
            if (!$onboarding) {
                return ToolResult::error('NOT_FOUND', 'Onboarding-Kandidat nicht gefunden.');
            }

            // Stellen-Check
            if ($interview->hcm_job_title_id && $onboarding->hcm_job_title_id) {
                if ((int)$interview->hcm_job_title_id !== (int)$onboarding->hcm_job_title_id) {
                    return ToolResult::error('VALIDATION_ERROR', 'Kandidat ist nicht auf derselben Stelle wie der Interview-Termin.');
                }
            }

            // Duplikat-Check
            $existing = HcmInterviewBooking::where('hcm_interview_id', $interviewId)
                ->where('hcm_onboarding_id', $onboardingId)
                ->exists();
            if ($existing) {
                return ToolResult::error('DUPLICATE', 'Dieser Kandidat ist bereits für diesen Termin gebucht.');
            }

            // Max-Teilnehmer-Check
            if ($interview->max_participants) {
                $currentCount = HcmInterviewBooking::where('hcm_interview_id', $interviewId)
                    ->whereNotIn('status', ['cancelled'])
                    ->count();
                if ($currentCount >= $interview->max_participants) {
                    return ToolResult::error('CAPACITY_REACHED', "Maximale Teilnehmerzahl ({$interview->max_participants}) bereits erreicht.");
                }
            }

            $booking = HcmInterviewBooking::create([
                'hcm_interview_id' => $interviewId,
                'hcm_onboarding_id' => $onboardingId,
                'status' => $arguments['status'] ?? 'registered',
                'notes' => $arguments['notes'] ?? null,
                'booked_at' => now(),
                'team_id' => $teamId,
                'created_by_user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $booking->id,
                'uuid' => $booking->uuid,
                'interview_id' => $interviewId,
                'onboarding_id' => $onboardingId,
                'status' => $booking->status,
                'message' => 'Kandidat erfolgreich gebucht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Buchen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'interview_bookings', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
