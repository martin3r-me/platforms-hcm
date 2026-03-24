<?php

namespace Platform\Hcm\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Tools\Concerns\ResolvesHcmTeam;

class UpdateInterviewTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHcmTeam;

    public function getName(): string
    {
        return 'hcm.interviews.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /hcm/interviews/{id} - Aktualisiert einen Interview-Termin. Parameter: interview_id (required). Optional: alle Felder inkl. interviewer_ids (Array, überschreibt bestehende).';
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
                    'description' => 'ID des Interviews (ERFORDERLICH).',
                ],
                'interview_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Gesprächsart-ID.',
                ],
                'hcm_job_title_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Stelle.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Titel.',
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
                    'description' => 'Optional: Startzeitpunkt.',
                ],
                'ends_at' => [
                    'type' => 'string',
                    'description' => 'Optional: Endzeitpunkt.',
                ],
                'min_participants' => [
                    'type' => 'integer',
                    'description' => 'Optional: Min. Teilnehmer.',
                ],
                'max_participants' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max. Teilnehmer.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Status (planned/confirmed/cancelled/completed).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Aktiv-Status.',
                ],
                'interviewer_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von User-IDs als Interviewer (ersetzt bestehende).',
                ],
                'reminder_wa_template_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines APPROVED WhatsApp-Templates für Erinnerung. Auf null setzen zum Entfernen.',
                ],
                'reminder_hours_before' => [
                    'type' => 'integer',
                    'description' => 'Optional: Stunden vor dem Termin für Erinnerung.',
                ],
            ],
            'required' => ['interview_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'interview_id', HcmInterview::class, 'NOT_FOUND', 'Interview nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $interview = $found['model'];

            if ((int)$interview->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Interview.');
            }

            $fields = ['interview_type_id', 'hcm_job_title_id', 'title', 'description', 'location', 'starts_at', 'ends_at', 'min_participants', 'max_participants', 'status', 'is_active', 'reminder_wa_template_id', 'reminder_hours_before'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $interview->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            $interview->save();

            if (array_key_exists('interviewer_ids', $arguments)) {
                $interview->interviewers()->sync(array_map('intval', $arguments['interviewer_ids'] ?? []));
            }

            return ToolResult::success([
                'id' => $interview->id,
                'uuid' => $interview->uuid,
                'title' => $interview->title,
                'status' => $interview->status,
                'message' => 'Interview-Termin erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Interviews: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['hcm', 'interviews', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
