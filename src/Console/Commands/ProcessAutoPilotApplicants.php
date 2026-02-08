<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\Authenticatable;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsChannelContext;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Hcm\Models\HcmApplicant;
use Platform\Hcm\Models\HcmApplicantSettings;
use Platform\Hcm\Models\HcmAutoPilotLog;
use Platform\Hcm\Models\HcmAutoPilotState;

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

            $waitingForApplicantStateId = HcmAutoPilotState::where('code', 'waiting_for_applicant')->whereNull('team_id')->value('id');
            $completedStateId = HcmAutoPilotState::where('code', 'completed')->whereNull('team_id')->value('id');

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
                $preferredChannel = $this->loadPreferredChannel($applicant);

                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🤖 Bewerbung #{$applicant->id} → Owner: {$owner->name} (user_id={$owner->id})");
                $this->line("Team: " . ($applicant->team?->name ?? '—'));
                $this->line("Model: {$model}");
                $this->line("Status: " . ($applicant->applicantStatus?->name ?? '—'));
                $this->line("AutoPilot-State: " . ($applicant->autoPilotState?->name ?? 'nicht gesetzt'));
                $this->line("Kontakte: " . count($contactInfo));
                $this->line("Extra-Fields: " . count($extraFields));
                $this->line("Threads: " . count($threadsSummary));
                $this->line("Bevorzugter Kanal: " . ($preferredChannel ? "{$preferredChannel['name']} ({$preferredChannel['sender_identifier']})" : '—'));

                if ($dryRun) {
                    continue;
                }

                // Snapshot state before run
                $oldStateId = $applicant->auto_pilot_state_id;
                $oldStateName = $applicant->autoPilotState?->name;

                // Log run_started
                $this->logAutoPilot($applicant, 'run_started', 'AutoPilot-Run gestartet', [
                    'state' => $oldStateName ?? 'nicht gesetzt',
                    'progress' => $applicant->progress,
                    'threads_count' => count($threadsSummary),
                    'preferred_channel' => $preferredChannel['name'] ?? null,
                ]);

                $contextTeam = $applicant->team;
                $this->impersonateForTask($owner, $contextTeam);
                $toolContext = new ToolContext($owner, $contextTeam, [
                    'context_model' => get_class($applicant),
                    'context_model_id' => $applicant->id,
                ]);

                $messages = $this->buildAgentMessages($applicant, $owner, $contactInfo, $extraFields, $threadsSummary, $preferredChannel, $waitingForApplicantStateId, $completedStateId);

                try {
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
                } catch (\Throwable $e) {
                    $this->logAutoPilot($applicant, 'error', 'Fehler beim LLM-Run: ' . $e->getMessage());
                    $this->error("❌ Bewerbung #{$applicant->id}: " . $e->getMessage());
                    continue;
                }

                $iterations = (int)($result['iterations'] ?? 0);
                $hitMaxIterations = $result['previous_response_id'] !== null;
                $lastToolCallNames = array_map(fn($c) => $c['name'] ?? '?', $result['last_tool_calls'] ?? []);

                $this->logAutoPilot($applicant, 'run_completed', "Run: {$iterations} Iteration(en)" . ($hitMaxIterations ? ' (max erreicht)' : ''), [
                    'iterations' => $iterations,
                    'hit_max_iterations' => $hitMaxIterations,
                    'last_tool_calls' => $lastToolCallNames,
                ]);
                $this->line("  Iterationen: {$iterations}/{$maxIterations}" . ($hitMaxIterations ? ' ⚠️ MAX' : ''));

                // Link new threads created during the run
                $linkedThreads = $this->linkNewThreadsToApplicant($applicant, $contactInfo);
                if ($linkedThreads > 0) {
                    $this->line("  Threads verknüpft: {$linkedThreads}");
                }

                // Reload and check end state
                $applicant->refresh();
                $applicant->loadMissing(['autoPilotState']);

                // Log LLM response as note
                $notes = trim((string)($result['assistant'] ?? ''));
                if ($notes !== '') {
                    $this->logAutoPilot($applicant, 'note', $notes);
                }

                if ($applicant->auto_pilot_completed_at !== null) {
                    $this->logAutoPilot($applicant, 'completed', 'AutoPilot abgeschlossen', [
                        'from_state' => $oldStateName,
                        'to_state' => $applicant->autoPilotState?->name ?? 'completed',
                    ]);
                    $this->info("✅ Bewerbung #{$applicant->id}: abgeschlossen (auto_pilot_completed_at gesetzt).");
                    continue;
                }

                if ($applicant->auto_pilot_state_id !== $oldStateId) {
                    $newStateName = $applicant->autoPilotState?->name ?? '?';
                    $this->logAutoPilot($applicant, 'state_changed', "State geändert: {$oldStateName} → {$newStateName}", [
                        'from_state_id' => $oldStateId,
                        'to_state_id' => $applicant->auto_pilot_state_id,
                        'from_state' => $oldStateName,
                        'to_state' => $newStateName,
                    ]);
                    $this->info("ℹ️ Bewerbung #{$applicant->id}: Fortschritt (State → {$newStateName}).");
                    continue;
                }

                // Nothing happened — append notes
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
            $teamId = $applicant->team_id;
            if (!$teamId) { return []; }

            if (!class_exists(CommsEmailThread::class)) {
                return [];
            }

            $emails = [];
            foreach ($contactInfo as $contact) {
                foreach ($contact['emails'] ?? [] as $email) {
                    $emails[] = $email['email'];
                }
            }

            $query = CommsEmailThread::query()
                ->where('team_id', $teamId)
                ->where(function ($q) use ($applicant, $emails) {
                    // Bereits verknüpfte Threads
                    $q->where(function ($q2) use ($applicant) {
                        $q2->where('context_model', get_class($applicant))
                            ->where('context_model_id', $applicant->id);
                    });
                    // ODER Threads mit passender Email-Adresse
                    if (!empty($emails)) {
                        $q->orWhere(function ($q2) use ($emails) {
                            $q2->where(function ($q3) use ($emails) {
                                foreach ($emails as $email) {
                                    $q3->orWhere('last_inbound_from_address', $email);
                                    $q3->orWhere('last_outbound_to_address', $email);
                                }
                            });
                        });
                    }
                })
                ->orderByDesc(DB::raw('COALESCE(last_inbound_at, last_outbound_at, updated_at)'))
                ->limit(10)
                ->get();

            return $query->map(fn ($t) => [
                'thread_id' => $t->id,
                'channel_id' => $t->comms_channel_id,
                'subject' => $t->subject,
                'counterpart' => $t->last_inbound_from_address ?: $t->last_outbound_to_address,
                'last_message_at' => ($t->last_inbound_at ?: $t->last_outbound_at)?->toIso8601String(),
                'last_inbound_at' => $t->last_inbound_at?->toIso8601String(),
                'last_outbound_at' => $t->last_outbound_at?->toIso8601String(),
                'is_linked' => $t->context_model === get_class($applicant) && $t->context_model_id === $applicant->id,
            ])->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadPreferredChannel(HcmApplicant $applicant): ?array
    {
        try {
            $teamId = $applicant->team_id;
            if (!$teamId) { return null; }

            if (!class_exists(HcmApplicantSettings::class) || !class_exists(CommsChannelContext::class)) {
                return null;
            }

            $settings = HcmApplicantSettings::where('team_id', $teamId)->first();
            if (!$settings) { return null; }

            $context = CommsChannelContext::where('context_model', get_class($settings))
                ->where('context_model_id', $settings->id)
                ->first();

            if (!$context) { return null; }

            $channel = CommsChannel::where('id', $context->comms_channel_id)
                ->where('is_active', true)
                ->first();

            if (!$channel) { return null; }

            return [
                'comms_channel_id' => $channel->id,
                'name' => $channel->name,
                'sender_identifier' => $channel->sender_identifier,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function linkNewThreadsToApplicant(HcmApplicant $applicant, array $contactInfo): int
    {
        $emails = [];
        foreach ($contactInfo as $contact) {
            foreach ($contact['emails'] ?? [] as $email) {
                $emails[] = $email['email'];
            }
        }
        if (empty($emails)) { return 0; }

        $teamId = $applicant->team_id;
        if (!$teamId) { return 0; }

        try {
            $updated = CommsEmailThread::query()
                ->where('team_id', $teamId)
                ->whereNull('context_model')
                ->where(function ($q) use ($emails) {
                    foreach ($emails as $email) {
                        $q->orWhere('last_outbound_to_address', $email);
                        $q->orWhere('last_inbound_from_address', $email);
                    }
                })
                ->where('created_at', '>=', now()->subMinutes(30))
                ->update([
                    'context_model' => get_class($applicant),
                    'context_model_id' => $applicant->id,
                ]);

            if ($updated > 0) {
                $this->logAutoPilot($applicant, 'note', "{$updated} neue(r) Thread(s) mit Bewerber verknüpft");
            }

            return $updated;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function logAutoPilot(HcmApplicant $applicant, string $type, string $summary, ?array $details = null): void
    {
        try {
            HcmAutoPilotLog::create([
                'hcm_applicant_id' => $applicant->id,
                'type' => $type,
                'summary' => $summary,
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            // ignore — logging should never break the run
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
        array $threadsSummary,
        ?array $preferredChannel,
        ?int $waitingForApplicantStateId = null,
        ?int $completedStateId = null
    ): array {
        $system = "Du bist {$owner->name} und bearbeitest automatisch Bewerbungen.\n"
            . "Du arbeitest im Namen des HR-Verantwortlichen — Kommunikation soll persönlich wirken.\n"
            . "Du arbeitest vollständig autonom (kein Rückfragen-Dialog mit einem Menschen).\n"
            . "Antworte und schreibe Notizen immer auf Deutsch.\n\n"
            . "GRUNDREGEL — HANDELN, NICHT BESCHREIBEN:\n"
            . "Du bist ein autonomer Agent. Du FÜHRST Aktionen AUS über Tool-Calls (Function Calling).\n"
            . "Du schreibst KEINE Reports, KEINE Zusammenfassungen, KEINE Vorschläge.\n"
            . "Jede deiner Antworten MUSS Tool-Calls enthalten — reiner Text ohne Tool-Call ist ein Fehler.\n"
            . "Dein Output ist NICHT für einen Menschen gedacht. Dein Output sind Tool-Calls.\n\n"
            . "ES GIBT VIER MÖGLICHE ERGEBNISSE:\n"
            . "A) Bewerbung VOLLSTÄNDIG → Alle Pflichtfelder ausgefüllt, CRM-Kontakt verknüpft → State auf 'completed' setzen.\n"
            . "B) UNVOLLSTÄNDIG, ERSTMALIG → Pflichtfelder fehlen, kein bestehender Thread zum Bewerber\n"
            . "   → Neue Nachricht an Bewerber SENDEN und fehlende Infos anfordern → State auf 'waiting_for_applicant' setzen.\n"
            . "C) NEUE INFOS ERHALTEN → State ist 'waiting_for_applicant', Bewerber hat geantwortet mit verwertbaren Infos\n"
            . "   → ZUERST Infos per core.extra_fields.PUT in die Felder schreiben\n"
            . "   → DANN prüfen: alle Pflichtfelder gefüllt? → 'completed'. Noch was fehlt? → REPLY im bestehenden Thread und restliche Infos nachfragen.\n"
            . "D) WEITERHIN WARTEND → State ist 'waiting_for_applicant', keine neuen verwertbaren Infos → NICHTS tun. FERTIG.\n"
            . "   WICHTIG: Sende NIEMALS eine Nachricht wenn du bereits auf Antwort wartest und keine neue Antwort da ist.\n\n"
            . "VERBOTEN:\n"
            . "- Text-Antworten die beschreiben was du tun \"würdest\", \"könntest\" oder \"empfiehlst\"\n"
            . "- \"Vorgeschlagene Payloads\", \"Empfohlene Aktionen\" oder ähnliche Reports\n"
            . "- Zusammenfassungen des Ist-Zustands als Endprodukt\n"
            . "- Abwarten, Planen oder Analysieren ohne anschließende Tool-Calls\n\n"
            . "WICHTIG (Tool-Discovery):\n"
            . "- Du siehst anfangs nur Discovery-Tools (z.B. tools.GET, core.teams.GET).\n"
            . "- Wenn dir ein Tool fehlt, lade es per tools.GET nach.\n"
            . "  Beispiel: tools.GET {\"module\":\"hcm\",\"search\":\"applicants\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"crm\",\"search\":\"contacts\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"core\",\"search\":\"extra_fields\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"communication\",\"search\":\"messages\"}\n\n"
            . "DEIN ABLAUF (führe jeden Schritt sofort per Tool-Call aus):\n"
            . "1. tools.GET — lade alle benötigten Tools\n"
            . "2. CRM-Kontakt prüfen — falls keiner verknüpft: suchen/erstellen und verknüpfen\n"
            . "3. Extra-Fields laden — prüfen welche required (is_required=true) und leer sind\n"
            . "4. Kommunikations-Threads prüfen:\n"
            . "   → WENN threads_summary LEER ist (keine Threads): Überspringe Schritt 5-6, gehe direkt zu Schritt 7.\n"
            . "   → WENN threads_summary Einträge hat: Lade Nachrichten per core.comms.email_messages.GET und prüfe ob neue verwertbare Infos vom Bewerber eingegangen sind.\n"
            . "5. WENN neue Infos in Nachrichten gefunden → SOFORT per core.extra_fields.PUT in die Felder schreiben. Diesen Schritt NIEMALS überspringen!\n"
            . "6. Extra-Fields erneut prüfen — nach dem Schreiben: welche Pflichtfelder sind JETZT noch leer?\n"
            . "7. ENTSCHEIDUNG:\n"
            . "   → Alle Pflichtfelder gefüllt? → hcm.applicants.PUT mit auto_pilot_completed_at='now' UND auto_pilot_state_id={$completedStateId}. FERTIG.\n"
            . "   → Pflichtfelder fehlen, KEIN Thread in threads_summary? → NEUE Nachricht senden (siehe NEUER THREAD unten), fehlende Infos anfordern. DANACH SOFORT: hcm.applicants.PUT mit auto_pilot_state_id={$waitingForApplicantStateId}. FERTIG.\n"
            . "   → Pflichtfelder fehlen, Thread vorhanden, neue Infos verarbeitet? → REPLY im bestehenden Thread (nur thread_id + body), restliche fehlende Infos nachfragen. FERTIG.\n"
            . "   → Pflichtfelder fehlen, Thread vorhanden, KEINE neuen Infos? → Nichts tun. FERTIG.\n\n"
            . "KOMMUNIKATION / THREADS — WICHTIG:\n"
            . "- Die unten aufgeführten threads_summary enthalten bereits die richtigen Thread-IDs für diesen Bewerber.\n"
            . "- Verwende für Replies NUR die angegebenen Thread-IDs (thread_id).\n"
            . "- Erstelle KEINEN neuen Thread wenn bereits ein passender existiert.\n"
            . "- Threads mit is_linked=true sind bereits mit diesem Bewerber verknüpft.\n"
            . "- Der bevorzugte Kanal (Email, WhatsApp, etc.) wird unten angegeben — nutze diesen.\n\n"
            . "REPLY-WORKFLOW (bestehender Thread):\n"
            . "- Für Reply NUR diese Parameter: core.comms.email_messages.POST { \"thread_id\": <thread_id aus threads_summary>, \"body\": \"Dein Text\" }\n"
            . "- 'to' und 'subject' werden AUTOMATISCH aus dem Thread abgeleitet — NICHT mitsenden.\n"
            . "- NIEMALS einen neuen Thread erstellen wenn threads_summary bereits einen passenden Thread enthält (insb. mit last_outbound_at gesetzt).\n\n"
            . "NEUER THREAD (nur wenn threads_summary LEER ist — kein einziger Thread):\n"
            . "- Nimm die Email-Adresse aus crm_contacts → emails (bevorzugt is_primary=true).\n"
            . "- core.comms.email_messages.POST { \"comms_channel_id\": <bevorzugter Kanal aus preferred_channel>, \"to\": \"<email aus crm_contacts>\", \"subject\": \"<Betreff>\", \"body\": \"...\" }\n"
            . "- Wenn KEIN bevorzugter Kanal angegeben: erst core.comms.channels.GET aufrufen um einen aktiven Email-Kanal zu finden.\n";

        if ($preferredChannel) {
            $system .= "\nBEVORZUGTER KOMMUNIKATIONSKANAL:\n"
                . "- comms_channel_id = {$preferredChannel['comms_channel_id']}\n"
                . "- Absender: {$preferredChannel['sender_identifier']}\n"
                . "- Verwende diesen Kanal für neue Nachrichten. Du musst NICHT core.comms.channels.GET aufrufen.\n";
        }

        $system .= "\nSTATE-IDS (bereits aufgelöst, NICHT per Lookup suchen):\n"
            . "- waiting_for_applicant = {$waitingForApplicantStateId}\n"
            . "- completed = {$completedStateId}\n\n"
            . "ENDZUSTÄNDE — es gibt genau vier:\n"
            . "A) KOMPLETT: Alle Pflichtfelder ausgefüllt, Kontakt verknüpft.\n"
            . "   → EIN EINZIGER CALL: hcm.applicants.PUT {\"applicant_id\": {$applicant->id}, \"auto_pilot_completed_at\": \"now\", \"auto_pilot_state_id\": {$completedStateId}}\n"
            . "B) WARTE AUF BEWERBER (erstmalig): Pflichtfelder fehlen, neue Nachricht gesendet.\n"
            . "   → PFLICHT-Schritt SOFORT nach dem Senden: hcm.applicants.PUT {\"applicant_id\": {$applicant->id}, \"auto_pilot_state_id\": {$waitingForApplicantStateId}}\n"
            . "   DIESER SCHRITT DARF NIEMALS VERGESSEN WERDEN.\n"
            . "C) NEUE INFOS VERARBEITET: Infos geschrieben, aber noch Felder offen → Reply im Thread gesendet.\n"
            . "   → State bleibt 'waiting_for_applicant'. FERTIG.\n"
            . "D) WEITERHIN WARTEND: Keine neuen Infos, nichts zu tun.\n"
            . "   → Nichts ändern. KEINE Nachricht senden. FERTIG.\n\n"
            . "VERFÜGBARE TOOLS (per Discovery):\n"
            . "- hcm.applicant.GET, hcm.applicants.PUT\n"
            . "- hcm.applicant_contacts.POST\n"
            . "- crm.contacts.GET, crm.contacts.POST\n"
            . "- core.extra_fields.GET, core.extra_fields.PUT\n"
            . "- core.comms.channels.GET, core.comms.email_threads.GET\n"
            . "- core.comms.email_messages.GET, core.comms.email_messages.POST (Email, WhatsApp, etc.)\n";

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

        if ($preferredChannel) {
            $applicantDump['preferred_channel'] = $preferredChannel;
        }

        $user = "Bewerbung (JSON):\n"
            . json_encode($applicantDump, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "Führe jetzt alle notwendigen Schritte aus. Beginne SOFORT mit Tool-Calls.\n"
            . "Erster Schritt: tools.GET um die benötigten Tools zu laden.\n"
            . "Entweder ist die Bewerbung vollständig → abschließen. Oder es fehlen Infos → Nachricht senden.\n"
            . "Schreibe KEINEN Report — handle direkt.\n";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }
}
