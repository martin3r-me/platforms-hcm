<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Models\HcmInterviewType;
use Platform\Hcm\Models\HcmJobTitle;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class CreateInterviewTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interviews.POST';
    }

    public function getDescription(): string
    {
        return 'POST /hcm/interviews - Erstellt einen Interview-Termin. ERFORDERLICH: starts_at. Optional: interview_type_id, hcm_job_title_id (Stelle), title, location, interviewer_ids (Array von User-IDs).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'interview_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Gesprächsart-ID. Nutze "hcm.interview_types.GET".',
                ],
                'hcm_job_title_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Stelle, für die das Interview ist. Nutze "hcm.job_titles.GET".',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Titel des Termins.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'location' => [
                    'type' => 'string',
                    'description' => 'Optional: Ort.',
                ],
                'starts_at' => [
                    'type' => 'string',
                    'description' => 'Startzeitpunkt (ERFORDERLICH). ISO 8601 oder YYYY-MM-DD HH:MM.',
                ],
                'ends_at' => [
                    'type' => 'string',
                    'description' => 'Optional: Endzeitpunkt.',
                ],
                'min_participants' => [
                    'type' => 'integer',
                    'description' => 'Optional: Mindest-Teilnehmerzahl.',
                ],
                'max_participants' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Teilnehmerzahl.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Status (planned/confirmed/cancelled/completed). Default: planned.',
                ],
                'interviewer_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von User-IDs als Interviewer.',
                ],
                'reminder_wa_template_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines APPROVED WhatsApp-Templates für Erinnerung.',
                ],
                'reminder_hours_before' => [
                    'type' => 'integer',
                    'description' => 'Optional: Stunden vor dem Termin, zu denen die Erinnerung gesendet wird (z.B. 24).',
                ],
            ],
            'required' => ['starts_at'],
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

            $startsAt = $arguments['starts_at'] ?? null;
            if (!$startsAt) {
                return ToolResult::error('VALIDATION_ERROR', 'starts_at ist erforderlich.');
            }

            if (isset($arguments['interview_type_id'])) {
                $type = HcmInterviewType::where('team_id', $teamId)->find((int)$arguments['interview_type_id']);
                if (!$type) {
                    return ToolResult::error('VALIDATION_ERROR', 'Gesprächsart nicht gefunden.');
                }
            }

            if (isset($arguments['hcm_job_title_id'])) {
                $jobTitle = HcmJobTitle::where('team_id', $teamId)->find((int)$arguments['hcm_job_title_id']);
                if (!$jobTitle) {
                    return ToolResult::error('VALIDATION_ERROR', 'Stelle nicht gefunden.');
                }
            }

            $interview = HcmInterview::create([
                'interview_type_id' => isset($arguments['interview_type_id']) ? (int)$arguments['interview_type_id'] : null,
                'hcm_job_title_id' => isset($arguments['hcm_job_title_id']) ? (int)$arguments['hcm_job_title_id'] : null,
                'title' => $arguments['title'] ?? null,
                'description' => $arguments['description'] ?? null,
                'location' => $arguments['location'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $arguments['ends_at'] ?? null,
                'min_participants' => $arguments['min_participants'] ?? null,
                'max_participants' => $arguments['max_participants'] ?? null,
                'status' => $arguments['status'] ?? 'planned',
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'reminder_wa_template_id' => isset($arguments['reminder_wa_template_id']) ? (int)$arguments['reminder_wa_template_id'] : null,
                'reminder_hours_before' => isset($arguments['reminder_hours_before']) ? (int)$arguments['reminder_hours_before'] : null,
                'team_id' => $teamId,
                'created_by_user_id' => $context->user?->id,
            ]);

            if (!empty($arguments['interviewer_ids'])) {
                $interview->interviewers()->sync(array_map('intval', $arguments['interviewer_ids']));
            }

            return ToolResult::success([
                'id' => $interview->id,
                'uuid' => $interview->uuid,
                'title' => $interview->title,
                'starts_at' => $interview->starts_at?->toISOString(),
                'message' => 'Interview-Termin erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Interviews: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'interviews', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
