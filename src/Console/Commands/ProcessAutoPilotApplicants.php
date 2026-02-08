<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Auth\Authenticatable;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Hcm\Models\HcmApplicant;

class ProcessAutoPilotApplicants extends Command
{
    protected $signature = 'hcm:process-auto-pilot-applicants
        {--limit=5 : Maximale Anzahl Bewerbungen pro Run}
        {--max-runtime-seconds=1200 : Maximale Laufzeit pro Run (Sekunden)}
        {--applicant-id= : Optional: einzelne Bewerbung bearbeiten}
        {--dry-run : Zeigt nur, was bearbeitet würde}
        {--max-iterations=40 : Max. Tool-Loop Iterationen pro Bewerbung}
        {--max-output-tokens=2000 : Max. Output Tokens pro LLM Call}
        {--no-web-search : Deaktiviert web_search Tool}';

    protected $description = 'Bearbeitet Bewerbungen mit auto_pilot=true iterativ per LLM+Tools. Agiert im Namen des owned_by_user_id (HR-Verantwortlicher).';

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $limit = (int)$this->option('limit');
        if ($limit < 1) { $limit = 1; }
        if ($limit > 100) { $limit = 100; }

        $maxRuntimeSeconds = (int)$this->option('max-runtime-seconds');
        if ($maxRuntimeSeconds < 10) { $maxRuntimeSeconds = 10; }
        if ($maxRuntimeSeconds > 12 * 60 * 60) { $maxRuntimeSeconds = 12 * 60 * 60; }
        $deadline = Carbon::now()->addSeconds($maxRuntimeSeconds);

        $applicantId = $this->option('applicant-id');
        $applicantId = is_numeric($applicantId) ? (int)$applicantId : null;

        $maxIterations = (int)$this->option('max-iterations');
        if ($maxIterations < 1) { $maxIterations = 1; }
        if ($maxIterations > 200) { $maxIterations = 200; }

        $maxOutputTokens = (int)$this->option('max-output-tokens');
        if ($maxOutputTokens < 64) { $maxOutputTokens = 64; }
        if ($maxOutputTokens > 200000) { $maxOutputTokens = 200000; }

        $includeWebSearch = !$this->option('no-web-search');

        $lockTtlSeconds = max(6 * 60 * 60, $maxRuntimeSeconds + 60 * 60);
        $lock = Cache::lock('hcm:process-auto-pilot-applicants', $lockTtlSeconds);
        if (!$lock->get()) {
            $this->warn('⏳ Läuft bereits (Lock aktiv).');
            return Command::SUCCESS;
        }

        try {
            if ($dryRun) {
                $this->warn('🔍 DRY-RUN – es werden keine Daten geändert.');
            }

            $runner = AiToolLoopRunner::make();

            $processed = 0;
            $seenIds = [];
            $originalAuthUser = Auth::user();

            while ($processed < $limit) {
                if (Carbon::now()->greaterThanOrEqualTo($deadline)) {
                    $this->warn("⏱️ Zeitbudget erreicht ({$maxRuntimeSeconds}s). Rest macht der nächste Run.");
                    break;
                }

                $applicant = $this->nextAutoPilotApplicant($applicantId, $seenIds);
                if (!$applicant) {
                    if ($processed === 0) {
                        $this->info('✅ Keine offenen AutoPilot-Bewerbungen gefunden.');
                    }
                    break;
                }

                $seenIds[] = (int)$applicant->id;
                $processed++;

                $owner = $applicant->ownedByUser;
                if (!$owner) {
                    $this->line("• Bewerbung #{$applicant->id}: übersprungen (kein Owner).");
                    continue;
                }

                if (method_exists($owner, 'isAiUser') && $owner->isAiUser()) {
                    $this->line("• Bewerbung #{$applicant->id}: übersprungen (Owner ist AI-User).");
                    continue;
                }

                $model = $this->determineModel();

                $contactInfo = $this->loadContactInfo($applicant);
                $extraFields = $this->loadExtraFields($applicant);
                $threadsSummary = $this->loadThreadsSummary($applicant, $contactInfo);

                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🤖 Bewerbung #{$applicant->id} → Owner: {$owner->name} (user_id={$owner->id})");
                $this->line("Team: " . ($applicant->team?->name ?? '—'));
                $this->line("Model: {$model}");
                $this->line("Status: " . ($applicant->applicantStatus?->name ?? '—'));
                $this->line("AutoPilot-State: " . ($applicant->autoPilotState?->name ?? 'nicht gesetzt'));
                $this->line("Kontakte: " . count($contactInfo));
                $this->line("Extra-Fields: " . count($extraFields));

                if ($dryRun) {
                    continue;
                }

                $contextTeam = $applicant->team;
                $this->impersonateForTask($owner, $contextTeam);
                $toolContext = new ToolContext($owner, $contextTeam);

                $messages = $this->buildAgentMessages($applicant, $owner, $contactInfo, $extraFields, $threadsSummary);

                $result = $runner->run(
                    $messages,
                    $model,
                    $toolContext,
                    [
                        'max_iterations' => $maxIterations,
                        'max_output_tokens' => $maxOutputTokens,
                        'include_web_search' => $includeWebSearch,
                        'reasoning' => ['effort' => 'medium'],
                    ]
                );

                // Reload and check end state
                $applicant->refresh();
                $applicant->loadMissing(['autoPilotState']);

                if ($applicant->auto_pilot_completed_at !== null) {
                    $this->info("✅ Bewerbung #{$applicant->id}: abgeschlossen (auto_pilot_completed_at gesetzt).");
                    continue;
                }

                $oldStateId = $applicant->getOriginal('auto_pilot_state_id');
                if ($applicant->auto_pilot_state_id !== $oldStateId) {
                    $stateName = $applicant->autoPilotState?->name ?? '?';
                    $this->info("ℹ️ Bewerbung #{$applicant->id}: Fortschritt (State → {$stateName}).");
                    continue;
                }

                // Nothing happened — append notes
                $notes = trim((string)($result['assistant'] ?? ''));
                $this->warn("⚠️ Bewerbung #{$applicant->id}: keine Statusänderung.");

                if ($notes !== '') {
                    $existingNotes = trim((string)($applicant->notes ?? ''));
                    $stamp = Carbon::now()->format('Y-m-d H:i');
                    $block = "— — —\nAutoPilot ({$stamp})\n{$notes}";
                    $applicant->notes = $existingNotes !== '' ? "{$existingNotes}\n\n{$block}" : $block;
                    $applicant->save();
                }
            }

            // Restore auth
            if ($originalAuthUser instanceof Authenticatable) {
                Auth::setUser($originalAuthUser);
            } else {
                try { Auth::guard()->logout(); } catch (\Throwable $e) {}
            }

            $this->newLine();
            $this->info("✅ Fertig. Bearbeitet: {$processed} Bewerbung(en).");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Fehler: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            try { Auth::guard()->logout(); } catch (\Throwable $e) {}
            try { $lock->release(); } catch (\Throwable $e) {}
        }
    }

    private function nextAutoPilotApplicant(?int $applicantId, array $excludeIds = []): ?HcmApplicant
    {
        $query = HcmApplicant::query()
            ->with(['applicantStatus', 'autoPilotState', 'team', 'ownedByUser'])
            ->where('auto_pilot', true)
            ->whereNull('auto_pilot_completed_at')
            ->whereNotNull('owned_by_user_id');

        if ($applicantId) {
            $query->where('id', $applicantId);
        }

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', array_map('intval', $excludeIds));
        }

        return $query
            ->orderBy('updated_at', 'asc')
            ->first();
    }

    private function determineModel(): string
    {
        try {
            $provider = CoreAiProvider::where('key', 'openai')->where('is_active', true)->with('defaultModel')->first();
            $fallback = $provider?->defaultModel?->model_id;
            if (is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 'gpt-5.2';
    }

    private function impersonateForTask(User $user, ?Team $team): void
    {
        Auth::setUser($user);

        if ($team) {
            $user->current_team_id = (int)$team->id;
            $user->setRelation('currentTeamRelation', $team);
        }
    }

    private function loadContactInfo(HcmApplicant $applicant): array
    {
        try {
            $applicant->loadMissing([
                'crmContactLinks.contact.emailAddresses',
                'crmContactLinks.contact.phoneNumbers',
            ]);

            return $applicant->crmContactLinks->map(function ($link) {
                $c = $link->contact;
                if (!$c) { return null; }
                return [
                    'contact_id' => $c->id,
                    'full_name' => $c->full_name,
                    'emails' => $c->emailAddresses?->map(fn ($e) => [
                        'email' => $e->email_address,
                        'is_primary' => (bool)$e->is_primary,
                    ])->values()->toArray() ?? [],
                    'phones' => $c->phoneNumbers?->map(fn ($p) => [
                        'number' => $p->international,
                        'is_primary' => (bool)$p->is_primary,
                    ])->values()->toArray() ?? [],
                ];
            })->filter()->values()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadExtraFields(HcmApplicant $applicant): array
    {
        try {
            return $applicant->getExtraFieldsWithLabels();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadThreadsSummary(HcmApplicant $applicant, array $contactInfo): array
    {
        try {
            if (empty($contactInfo)) {
                return [];
            }

            // Collect all contact emails
            $emails = [];
            foreach ($contactInfo as $contact) {
                foreach ($contact['emails'] ?? [] as $email) {
                    $emails[] = $email['email'];
                }
            }

            if (empty($emails)) {
                return [];
            }

            // Search for email threads involving these addresses
            // The CommsEmailThread model may not exist yet — LLM can use tools to find threads at runtime
            $threadModelClass = 'Platform\\Core\\Models\\CommsEmailThread';
            if (!class_exists($threadModelClass)) {
                return [];
            }

            $teamId = $applicant->team_id;
            if (!$teamId) { return []; }

            $threads = $threadModelClass::query()
                ->where('team_id', $teamId)
                ->where(function ($q) use ($emails) {
                    foreach ($emails as $email) {
                        $q->orWhere('participants', 'LIKE', '%' . $email . '%');
                    }
                })
                ->orderByDesc('last_message_at')
                ->limit(5)
                ->get();

            return $threads->map(fn ($t) => [
                'thread_id' => $t->id,
                'subject' => $t->subject,
                'last_message_at' => $t->last_message_at?->toISOString(),
                'message_count' => $t->message_count ?? null,
            ])->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array{role:string, content:string}>
     */
    private function buildAgentMessages(
        HcmApplicant $applicant,
        User $owner,
        array $contactInfo,
        array $extraFields,
        array $threadsSummary
    ): array {
        $system = "Du bist {$owner->name} und bearbeitest automatisch Bewerbungen.\n"
            . "Du arbeitest im Namen des HR-Verantwortlichen — Kommunikation soll persönlich wirken.\n"
            . "Du arbeitest vollständig autonom (kein Rückfragen-Dialog mit einem Menschen).\n"
            . "Du darfst Tools verwenden (Function Calling). Nutze Tools, um Informationen zu sammeln und Aktionen auszuführen.\n"
            . "Antworte und schreibe Notizen immer auf Deutsch.\n\n"
            . "WICHTIG (Tool-Discovery):\n"
            . "- Du siehst anfangs nur Discovery-Tools (z.B. tools.GET, core.teams.GET).\n"
            . "- Wenn dir ein Tool fehlt, lade es per tools.GET nach.\n"
            . "  Beispiel: tools.GET {\"module\":\"hcm\",\"search\":\"applicants\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"crm\",\"search\":\"contacts\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"core\",\"search\":\"extra_fields\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"communication\",\"search\":\"email\"}\n\n"
            . "DEINE AUFGABE:\n"
            . "1. Prüfe ob ein CRM-Kontakt verknüpft ist. Wenn nicht:\n"
            . "   - Suche in CRM nach passenden Kontakten (Name, Email)\n"
            . "   - Verknüpfe gefundenen Kontakt ODER lege neuen an\n"
            . "   - Nutze: crm.contacts.GET, crm.contacts.POST, hcm.applicant_contacts.POST\n"
            . "2. Prüfe Extra-Fields: welche sind required, welche gefüllt?\n"
            . "   - Nutze: core.extra_fields.GET, core.extra_fields.PUT\n"
            . "3. Prüfe bestehende Email-Threads: gibt es neue Nachrichten mit Infos?\n"
            . "   - Nutze: core.comms.email_threads.GET, core.comms.email_messages.GET\n"
            . "4. Extrahiere Infos aus Nachrichten und fülle Felder\n"
            . "5. Ermittle Delta: was fehlt noch?\n"
            . "6. Falls etwas fehlt: schreibe dem Bewerber eine Email\n"
            . "   - Nutze: core.comms.channels.GET, core.comms.email_messages.POST\n"
            . "   - Bevorzuge existierende Threads für Replies\n"
            . "7. Setze auto_pilot_state entsprechend über hcm.applicants.PUT\n\n"
            . "ENDZUSTÄNDE — wähle genau einen:\n"
            . "1. KOMPLETT: Alle Felder ausgefüllt, Kontakt verknüpft.\n"
            . "   → hcm.applicants.PUT {\"applicant_id\": {$applicant->id}, \"auto_pilot_completed_at\": \"now\"}\n"
            . "   Setze auch auto_pilot_state_id auf den 'completed' State.\n"
            . "   (Nutze hcm.lookup.GET {\"lookup\": \"auto_pilot_states\", \"code\": \"completed\"} um die ID zu ermitteln.)\n"
            . "2. WARTE AUF BEWERBER: Email gesendet, warte auf Antwort.\n"
            . "   → hcm.applicants.PUT {\"applicant_id\": {$applicant->id}, \"auto_pilot_state_id\": <waiting_for_applicant ID>}\n"
            . "3. PRÜFUNG NÖTIG: Etwas stimmt nicht / brauche menschliche Entscheidung.\n"
            . "   → hcm.applicants.PUT {\"applicant_id\": {$applicant->id}, \"auto_pilot_state_id\": <review_needed ID>}\n\n"
            . "VERFÜGBARE TOOLS (per Discovery):\n"
            . "- hcm.applicant.GET, hcm.applicants.PUT\n"
            . "- hcm.applicant_contacts.POST\n"
            . "- crm.contacts.GET, crm.contacts.POST\n"
            . "- core.extra_fields.GET, core.extra_fields.PUT\n"
            . "- core.comms.channels.GET, core.comms.email_threads.GET\n"
            . "- core.comms.email_messages.GET, core.comms.email_messages.POST\n"
            . "- hcm.lookup.GET (für Status-IDs und auto_pilot_state-IDs)\n";

        $applicantDump = [
            'applicant_id' => $applicant->id,
            'uuid' => $applicant->uuid,
            'team_id' => $applicant->team_id,
            'team' => $applicant->team?->name,
            'status' => $applicant->applicantStatus ? [
                'id' => $applicant->applicantStatus->id,
                'name' => $applicant->applicantStatus->name,
            ] : null,
            'auto_pilot_state' => $applicant->autoPilotState ? [
                'id' => $applicant->autoPilotState->id,
                'code' => $applicant->autoPilotState->code,
                'name' => $applicant->autoPilotState->name,
            ] : null,
            'progress' => $applicant->progress,
            'notes' => $applicant->notes,
            'applied_at' => $applicant->applied_at?->toDateString(),
            'crm_contacts' => $contactInfo,
            'extra_fields' => $extraFields,
            'threads_summary' => $threadsSummary,
        ];

        $user = "Bewerbung (JSON):\n"
            . json_encode($applicantDump, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "Bitte bearbeite diese Bewerbung. Prüfe den aktuellen Stand und handle entsprechend.\n"
            . "Lade zuerst die Tools die du brauchst per tools.GET nach.\n";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }
}
