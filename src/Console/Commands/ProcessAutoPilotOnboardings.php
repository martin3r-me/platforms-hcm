<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\Authenticatable;
use Platform\Core\Contracts\ToolContext;
use Platform\Crm\Models\CommsChannel;
use Platform\Crm\Models\CommsEmailThread;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Hcm\Models\HcmAutoPilotLog;
use Platform\Hcm\Models\HcmOnboarding;

class ProcessAutoPilotOnboardings extends Command
{
    protected $signature = 'hcm:process-auto-pilot-onboardings
        {--limit=5 : Maximale Anzahl Onboardings pro Run}
        {--max-runtime-seconds=1200 : Maximale Laufzeit pro Run (Sekunden)}
        {--onboarding-id= : Optional: einzelnes Onboarding bearbeiten}
        {--dry-run : Zeigt nur, was bearbeitet würde}
        {--max-iterations=40 : Max. Tool-Loop Iterationen pro Onboarding}
        {--max-output-tokens=2000 : Max. Output Tokens pro LLM Call}
        {--no-web-search : Deaktiviert web_search Tool}';

    protected $description = 'Bearbeitet Onboardings mit auto_pilot=true iterativ per LLM+Tools. Agiert im Namen des owned_by_user_id (HR-Verantwortlicher).';

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

        $onboardingId = $this->option('onboarding-id');
        $onboardingId = is_numeric($onboardingId) ? (int)$onboardingId : null;

        $maxIterations = (int)$this->option('max-iterations');
        if ($maxIterations < 1) { $maxIterations = 1; }
        if ($maxIterations > 200) { $maxIterations = 200; }

        $maxOutputTokens = (int)$this->option('max-output-tokens');
        if ($maxOutputTokens < 64) { $maxOutputTokens = 64; }
        if ($maxOutputTokens > 200000) { $maxOutputTokens = 200000; }

        $lockTtlSeconds = max(6 * 60 * 60, $maxRuntimeSeconds + 60 * 60);
        $lockKey = $onboardingId
            ? "hcm:process-auto-pilot-onboarding:{$onboardingId}"
            : 'hcm:process-auto-pilot-onboardings';
        $lock = Cache::lock($lockKey, $lockTtlSeconds);
        if (!$lock->get()) {
            $this->warn('Läuft bereits (Lock aktiv).');
            return Command::SUCCESS;
        }

        try {
            if ($dryRun) {
                $this->warn('DRY-RUN – es werden keine Daten geändert.');
            }

            $runner = AiToolLoopRunner::make();

            $processed = 0;
            $seenIds = [];
            $originalAuthUser = Auth::user();

            while ($processed < $limit) {
                if (Carbon::now()->greaterThanOrEqualTo($deadline)) {
                    $this->warn("Zeitbudget erreicht ({$maxRuntimeSeconds}s). Rest macht der nächste Run.");
                    break;
                }

                $onboarding = $this->nextOnboarding($onboardingId, $seenIds);
                if (!$onboarding) {
                    if ($processed === 0) {
                        $this->info('Keine offenen AutoPilot-Onboardings gefunden.');
                    }
                    break;
                }

                $seenIds[] = (int)$onboarding->id;
                $processed++;

                $owner = $onboarding->ownedByUser;
                if (!$owner) {
                    $this->line("• Onboarding #{$onboarding->id}: übersprungen (kein Owner).");
                    continue;
                }

                if (method_exists($owner, 'isAiUser') && $owner->isAiUser()) {
                    $this->line("• Onboarding #{$onboarding->id}: übersprungen (Owner ist AI-User).");
                    continue;
                }

                $model = $this->determineModel();

                $contactInfo = $this->loadContactInfo($onboarding);
                $extraFields = $this->loadExtraFields($onboarding);
                $preferredChannel = $this->loadPreferredChannel($onboarding);
                $threadsSummary = $this->loadThreadsSummary($onboarding);

                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("Onboarding #{$onboarding->id} → Owner: {$owner->name} (user_id={$owner->id})");
                $this->line("Team: " . ($onboarding->team?->name ?? '—'));
                $this->line("Model: {$model}");
                $this->line("Kontakte: " . count($contactInfo));
                $this->line("Extra-Fields: " . count($extraFields));
                $this->line("Threads: " . count($threadsSummary));
                $this->line("Bevorzugter Kanal: " . ($preferredChannel ? "{$preferredChannel['name']} ({$preferredChannel['sender_identifier']})" : '—'));

                if ($dryRun) {
                    continue;
                }

                $scenario = $this->determineScenario($onboarding, $extraFields, $threadsSummary);
                $missingFields = $this->getMissingRequiredFields($extraFields);
                $this->line("  Scenario: {$scenario} | Fehlende Pflichtfelder: " . count($missingFields));

                $this->logAutoPilot($onboarding, 'scenario', "Scenario {$scenario}", [
                    'missing_required' => count($missingFields),
                    'has_threads' => !empty($threadsSummary),
                ]);

                // ===== Scenario A: Komplett → direkt abschließen (kein LLM) =====
                if ($scenario === 'A') {
                    $this->impersonateForTask($owner, $onboarding->team);
                    $onboarding->auto_pilot_completed_at = now();
                    $onboarding->save();
                    $this->logAutoPilot($onboarding, 'completed', 'Scenario A: Alle Pflichtfelder ausgefüllt.');
                    $this->info("  Scenario A → abgeschlossen.");
                    continue;
                }

                // ===== Scenario D: Wartend, keine neuen Infos → überspringen (kein LLM) =====
                if ($scenario === 'D') {
                    $this->logAutoPilot($onboarding, 'skipped', 'Scenario D: Warte auf Antwort, keine neuen Infos.');
                    $this->info("  Scenario D → übersprungen.");
                    continue;
                }

                // ===== Scenario B + C: LLM-Call =====
                $primaryEmail = $this->findPrimaryEmail($contactInfo);
                if (!$primaryEmail) {
                    $this->logAutoPilot($onboarding, 'warning', 'Keine Email-Adresse vorhanden — übersprungen.');
                    $this->warn("  Keine Email-Adresse → übersprungen.");
                    continue;
                }

                $contextTeam = $onboarding->team;
                $this->impersonateForTask($owner, $contextTeam);
                $toolContext = new ToolContext($owner, $contextTeam, [
                    'context_model' => get_class($onboarding),
                    'context_model_id' => $onboarding->id,
                ]);

                $preloadTools = [
                    'core.extra_fields.GET', 'core.extra_fields.PUT',
                    'core.comms.email_messages.GET', 'core.comms.email_messages.POST',
                    'crm.contacts.GET', 'crm.contacts.POST',
                ];
                $messages = $this->buildMessages(
                    $onboarding, $owner, $contactInfo, $extraFields, $missingFields,
                    $threadsSummary, $preferredChannel
                );

                $this->logAutoPilot($onboarding, 'run_started', "Scenario {$scenario}: LLM-Run", [
                    'preload_tools' => $preloadTools,
                ]);

                try {
                    $result = $runner->run($messages, $model, $toolContext, [
                        'max_iterations' => $maxIterations,
                        'max_output_tokens' => $maxOutputTokens,
                        'include_web_search' => false,
                        'reasoning' => ['effort' => 'medium'],
                        'preload_tools' => $preloadTools,
                        'on_iteration' => function (int $iter, array $toolNames, int $textLen) {
                            $this->line("    Iter {$iter}: " . (empty($toolNames) ? '(keine Tools)' : implode(', ', $toolNames)));
                        },
                    ]);
                } catch (\Throwable $e) {
                    $this->logAutoPilot($onboarding, 'error', 'LLM-Fehler: ' . $e->getMessage());
                    $this->error("  " . $e->getMessage());
                    continue;
                }

                // --- Ergebnis auswerten ---
                $iterations = (int)($result['iterations'] ?? 0);
                $allToolCallNames = $result['all_tool_call_names'] ?? [];
                $emailSent = in_array('core.comms.email_messages.POST', $allToolCallNames);

                $this->logAutoPilot($onboarding, 'run_completed', "Scenario {$scenario}: {$iterations} Iterationen", [
                    'iterations' => $iterations,
                    'all_tool_calls' => $allToolCallNames,
                    'email_sent' => $emailSent,
                ]);
                $this->line("  Iterationen: {$iterations} | Tools: " . (empty($allToolCallNames) ? '(keine)' : implode(', ', $allToolCallNames)));
                $this->line("  Email: " . ($emailSent ? 'JA' : 'NEIN'));

                // Threads verknüpfen
                $linkedThreads = $this->linkNewThreadsToOnboarding($onboarding, $contactInfo, $preferredChannel);
                if ($linkedThreads > 0) { $this->line("  Threads verknüpft: {$linkedThreads}"); }

                // Reload
                $onboarding->refresh();

                // Guard: LLM darf auto_pilot nicht abschalten
                if (!$onboarding->auto_pilot) {
                    $onboarding->auto_pilot = true;
                    $onboarding->save();
                    $this->logAutoPilot($onboarding, 'warning', 'LLM hat auto_pilot deaktiviert — wurde zurückgesetzt.');
                    $this->warn("  auto_pilot wurde vom LLM deaktiviert → zurückgesetzt.");
                }

                // Notes loggen
                $notes = trim((string)($result['assistant'] ?? ''));
                if ($notes !== '') {
                    $this->logAutoPilot($onboarding, 'note', $notes);
                }

                // Completed prüfen
                if ($onboarding->auto_pilot_completed_at !== null) {
                    // Guard: Prüfe ob Pflichtfelder tatsächlich gefüllt sind
                    $stillMissing = $this->getMissingRequiredFields($this->loadExtraFields($onboarding));
                    if (!empty($stillMissing)) {
                        $missingNames = array_column($stillMissing, 'label');
                        $onboarding->auto_pilot_completed_at = null;
                        $onboarding->save();
                        $this->logAutoPilot($onboarding, 'warning',
                            'LLM hat completed gesetzt, aber Pflichtfelder fehlen noch: ' . implode(', ', $missingNames));
                        $this->warn("  Completed zurückgesetzt — fehlende Felder: " . implode(', ', $missingNames));
                    } else {
                        $this->logAutoPilot($onboarding, 'completed', 'AutoPilot abgeschlossen.');
                        $this->info("  Abgeschlossen.");
                    }
                } else {
                    $this->info("  Run beendet.");
                }
            }

            // Restore auth
            if ($originalAuthUser instanceof Authenticatable) {
                Auth::setUser($originalAuthUser);
            } else {
                try { Auth::guard()->logout(); } catch (\Throwable $e) {}
            }

            $this->newLine();
            $this->info("Fertig. Bearbeitet: {$processed} Onboarding(s).");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            try { Auth::guard()->logout(); } catch (\Throwable $e) {}
            try { $lock->release(); } catch (\Throwable $e) {}
        }
    }

    private function nextOnboarding(?int $onboardingId, array $excludeIds = []): ?HcmOnboarding
    {
        $query = HcmOnboarding::query()
            ->with(['team', 'ownedByUser'])
            ->where('auto_pilot', true)
            ->whereNull('auto_pilot_completed_at')
            ->whereNotNull('owned_by_user_id');

        if ($onboardingId) {
            $query->where('id', $onboardingId);
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

    private function loadContactInfo(HcmOnboarding $onboarding): array
    {
        try {
            $onboarding->loadMissing([
                'crmContactLinks.contact.emailAddresses',
                'crmContactLinks.contact.phoneNumbers',
            ]);

            return $onboarding->crmContactLinks->map(function ($link) {
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

    private function loadExtraFields(HcmOnboarding $onboarding): array
    {
        try {
            return $onboarding->getExtraFieldsWithLabels();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadThreadsSummary(HcmOnboarding $onboarding): array
    {
        try {
            if (!class_exists(CommsEmailThread::class)) {
                return [];
            }

            $query = CommsEmailThread::query()
                ->where('context_model', get_class($onboarding))
                ->where('context_model_id', $onboarding->id)
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
                'is_linked' => true,
            ])->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadPreferredChannel(HcmOnboarding $onboarding): ?array
    {
        try {
            $channel = $onboarding->preferredCommsChannel;
            if (!$channel || !$channel->is_active) {
                return null;
            }

            return [
                'comms_channel_id' => $channel->id,
                'name' => $channel->name,
                'sender_identifier' => $channel->sender_identifier,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function linkNewThreadsToOnboarding(HcmOnboarding $onboarding, array $contactInfo, ?array $preferredChannel = null): int
    {
        $emails = [];
        foreach ($contactInfo as $contact) {
            foreach ($contact['emails'] ?? [] as $email) {
                $emails[] = $email['email'];
            }
        }
        if (empty($emails)) { return 0; }

        $teamId = $onboarding->team_id;
        if (!$teamId) { return 0; }

        $channelId = $preferredChannel['comms_channel_id'] ?? null;
        if (!$channelId) { return 0; }

        try {
            $updated = CommsEmailThread::query()
                ->where('comms_channel_id', $channelId)
                ->whereNull('context_model')
                ->where(function ($q) use ($emails) {
                    foreach ($emails as $email) {
                        $q->orWhere('last_outbound_to_address', $email);
                        $q->orWhere('last_inbound_from_address', $email);
                    }
                })
                ->where('created_at', '>=', now()->subMinutes(30))
                ->update([
                    'context_model' => get_class($onboarding),
                    'context_model_id' => $onboarding->id,
                ]);

            if ($updated > 0) {
                $this->logAutoPilot($onboarding, 'note', "{$updated} neue(r) Thread(s) mit Onboarding verknüpft");
            }

            return $updated;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function logAutoPilot(HcmOnboarding $onboarding, string $type, string $summary, ?array $details = null): void
    {
        try {
            HcmAutoPilotLog::create([
                'hcm_onboarding_id' => $onboarding->id,
                'type' => $type,
                'summary' => $summary,
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            // ignore — logging should never break the run
        }
    }

    // ===== Scenario Routing =====

    private function determineScenario(HcmOnboarding $onboarding, array $extraFields, array $threadsSummary): string
    {
        $missingRequired = $this->getMissingRequiredFields($extraFields);

        // A: Alle Pflichtfelder gefüllt → completed
        if (empty($missingRequired)) {
            return 'A';
        }

        $hasThreads = !empty($threadsSummary);

        // B: Pflichtfelder fehlen, keine Threads → Email senden
        if (!$hasThreads) {
            return 'B';
        }

        // C: Pflichtfelder fehlen, Threads vorhanden, neue Inbound → Infos extrahieren
        if ($this->hasNewInboundMessages($threadsSummary)) {
            return 'C';
        }

        // D: Pflichtfelder fehlen, Threads vorhanden, keine neuen Infos → skip
        return 'D';
    }

    private function getMissingRequiredFields(array $extraFields): array
    {
        return array_filter($extraFields, fn(array $f) =>
            !empty($f['is_required']) && ($f['value'] === null || $f['value'] === '' || $f['value'] === [])
        );
    }

    private function hasNewInboundMessages(array $threadsSummary): bool
    {
        foreach ($threadsSummary as $thread) {
            $inbound = $thread['last_inbound_at'] ?? null;
            $outbound = $thread['last_outbound_at'] ?? null;
            if ($inbound !== null && ($outbound === null || $inbound > $outbound)) {
                return true;
            }
        }
        return false;
    }

    private function findPrimaryEmail(array $contactInfo): ?string
    {
        $fallback = null;
        foreach ($contactInfo as $contact) {
            foreach ($contact['emails'] ?? [] as $email) {
                if ($email['is_primary'] ?? false) return $email['email'];
                if ($fallback === null) $fallback = $email['email'];
            }
        }
        return $fallback;
    }

    // ===== Prompt Building =====

    private function buildMessages(
        HcmOnboarding $onboarding, User $owner, array $contactInfo,
        array $extraFields, array $missingFields, array $threadsSummary,
        ?array $preferredChannel
    ): array {
        $contactName = $contactInfo[0]['full_name'] ?? 'neuer Mitarbeiter';
        $primaryEmail = $this->findPrimaryEmail($contactInfo);
        $publicUrl = $onboarding->getPublicUrl();

        $teamName = $onboarding->team?->name ?? 'HR';
        $system = "Du bist ein HR-Assistent von {$teamName}.\n"
            . "Du bearbeitest das Onboarding von {$contactName} ({$primaryEmail}).\n"
            . "Du arbeitest autonom — handle per Tool-Calls, schreibe keine Reports.\n"
            . "Kommuniziere immer auf Deutsch, persönlich und professionell.\n"
            . "Unterschreibe Nachrichten IMMER nur mit \"{$teamName}\" — NIEMALS mit einem persönlichen Namen.\n\n"
            . "DEINE AUFGABE:\n"
            . "Prüfe ob alle Pflichtfelder ausgefüllt sind. Extrahiere Infos aus vorhandenen Daten (CRM, Threads).\n"
            . "- Lies bestehende Nachrichten-Threads, extrahiere alle verwertbaren Infos.\n"
            . "- Schreibe alles was du findest in die Extra-Felder des Onboardings (core_extra_fields_PUT).\n"
            . "- Du kannst auch den CRM-Kontakt aktualisieren wenn du relevante Daten findest.\n"
            . "- Wenn du dem neuen Mitarbeiter schreiben musst, nutze den bevorzugten Kanal.\n"
            . "- Wenn alle Pflichtfelder gefüllt sind, setze auto_pilot_completed_at.\n\n"
            . "WICHTIG — FORMULAR-LINK STATT EINZELFRAGEN:\n"
            . "- Frage den neuen Mitarbeiter NIEMALS nach einzelnen Feldern in der Nachricht!\n"
            . "- Stattdessen: Teile dem neuen Mitarbeiter den Link zum Online-Formular mit, wo er alle fehlenden Daten selbst eintragen kann.\n"
            . "- Formular-Link: {$publicUrl}\n"
            . "- Die Nachricht soll KURZ und FREUNDLICH sein — kein Verhör, keine Feld-für-Feld-Abfrage.\n"
            . "- Beispiel für eine gute Nachricht:\n"
            . "  \"Hallo [Name], herzlich willkommen! Damit wir Ihr Onboarding abschließen können, "
            . "bitten wir Sie, noch einige Angaben über unser Online-Formular zu ergänzen: {$publicUrl} — Vielen Dank!\"\n"
            . "- Bei Follow-ups (wenn der Mitarbeiter schon kontaktiert wurde aber noch Felder fehlen):\n"
            . "  \"Hallo [Name], uns fehlen noch einige Angaben. Bitte ergänzen Sie diese hier: {$publicUrl} — Danke!\"\n"
            . "- NIEMALS eine Liste der fehlenden Felder in die Nachricht schreiben!\n\n"
            . "CRM-ABGLEICH — VOR DEM KONTAKTIEREN:\n"
            . "- BEVOR du den neuen Mitarbeiter kontaktierst, gleiche die CRM-Kontaktdaten mit den Extra-Feldern ab!\n"
            . "- Die crm_contacts unten enthalten bereits Email-Adressen, Telefonnummern, Namen etc.\n"
            . "- Prüfe ob ein leeres Pflicht-Extra-Feld mit vorhandenen CRM-Daten gefüllt werden kann:\n"
            . "  z.B. Extra-Feld \"E-Mail\" ← crm_contacts.emails, Extra-Feld \"Telefon\" ← crm_contacts.phones,\n"
            . "  Extra-Feld \"Vorname\"/\"Nachname\" ← crm_contacts.full_name, etc.\n"
            . "- Lade die CRM-Kontaktdaten per crm.contacts.GET (contact_id aus crm_contacts) um weitere Felder zu prüfen:\n"
            . "  Geburtsdatum, Adresse, Anrede, Titel etc.\n"
            . "- Schreibe passende Werte per core.extra_fields.PUT in die Extra-Felder.\n"
            . "- Erst NACH diesem Abgleich entscheiden ob noch Pflichtfelder fehlen und der Mitarbeiter kontaktiert werden muss.\n\n"
            . "EXTRA-FIELDS SCHREIBEN — WICHTIGE REGELN:\n"
            . "- core.extra_fields.PUT erwartet: {\"fields\": {\"feldname\": \"wert\", ...}}\n"
            . "- Sende NUR Felder mit einem tatsächlichen Wert. NIEMALS null oder \"\" als Wert senden!\n"
            . "- null oder \"\" LÖSCHT den bestehenden Wert des Feldes — das ist fast nie gewollt.\n"
            . "- Wenn du keinen Wert für ein Feld hast, lasse es komplett weg (nicht mitsenden).\n"
            . "- Die Feld-Keys findest du in den extra_fields unten (das \"key\"-Attribut jedes Feldes).\n"
            . "- Nutze exakt diese Keys, keine eigenen Namen oder Labels.\n\n"
            . "GRUNDREGEL — HANDELN, NICHT BESCHREIBEN:\n"
            . "Du bist ein autonomer Agent. Du FÜHRST Aktionen AUS über Tool-Calls (Function Calling).\n"
            . "Du schreibst KEINE Reports, KEINE Zusammenfassungen, KEINE Vorschläge.\n"
            . "Jede deiner Antworten MUSS Tool-Calls enthalten — reiner Text ohne Tool-Call ist ein Fehler.\n"
            . "Dein Output ist NICHT für einen Menschen gedacht. Dein Output sind Tool-Calls.\n\n"
            . "ES GIBT VIER MÖGLICHE ERGEBNISSE:\n"
            . "A) KOMPLETT → Alle Pflichtfelder ausgefüllt, CRM-Kontakt verknüpft → auto_pilot_completed_at setzen.\n"
            . "B) UNVOLLSTÄNDIG, ERSTMALIG → Pflichtfelder fehlen, kein bestehender Thread\n"
            . "   → Neue Nachricht an neuen Mitarbeiter SENDEN und fehlende Infos anfordern.\n"
            . "C) NEUE INFOS ERHALTEN → Threads vorhanden, neue Antwort mit verwertbaren Infos\n"
            . "   → ZUERST Infos per core.extra_fields.PUT in die Felder schreiben\n"
            . "   → DANN prüfen: alle Pflichtfelder gefüllt? → completed. Noch was fehlt? → REPLY im bestehenden Thread.\n"
            . "D) WEITERHIN WARTEND → Threads vorhanden, keine neuen verwertbaren Infos → NICHTS tun. FERTIG.\n"
            . "   WICHTIG: Sende NIEMALS eine Nachricht wenn du bereits auf Antwort wartest und keine neue Antwort da ist.\n\n"
            . "VERBOTEN:\n"
            . "- Text-Antworten die beschreiben was du tun \"würdest\", \"könntest\" oder \"empfiehlst\"\n"
            . "- \"Vorgeschlagene Payloads\", \"Empfohlene Aktionen\" oder ähnliche Reports\n"
            . "- Zusammenfassungen des Ist-Zustands als Endprodukt\n"
            . "- Abwarten, Planen oder Analysieren ohne anschließende Tool-Calls\n\n"
            . "WICHTIG (Tool-Discovery):\n"
            . "- Du siehst anfangs nur Discovery-Tools (z.B. tools.GET, core.teams.GET).\n"
            . "- Wenn dir ein Tool fehlt, lade es per tools.GET nach.\n"
            . "  Beispiel: tools.GET {\"module\":\"core\",\"search\":\"extra_fields\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"crm\",\"search\":\"contacts\"}\n"
            . "  Beispiel: tools.GET {\"module\":\"communication\",\"search\":\"messages\"}\n\n";

        // Thread-Hinweise
        if (!empty($threadsSummary)) {
            $system .= "KOMMUNIKATION:\n"
                . "- Es gibt bereits Threads mit dem neuen Mitarbeiter (siehe Daten unten).\n"
                . "- Für Replies im bestehenden Thread: nur thread_id + body (KEIN to, KEIN subject).\n"
                . "- Sende den Formular-Link ({$publicUrl}) — KEINE Auflistung einzelner Felder.\n\n";
        } else {
            $system .= "KOMMUNIKATION:\n"
                . "- Es gibt noch keinen Thread mit dem neuen Mitarbeiter.\n"
                . "- Für neue Nachrichten: comms_channel_id + to + subject + body.\n"
                . "- Sende eine kurze, freundliche Nachricht mit dem Formular-Link: {$publicUrl}\n"
                . "- KEINE einzelnen Felder aufzählen oder abfragen!\n\n";
        }

        // Bevorzugter Kanal
        if ($preferredChannel) {
            $system .= "STANDARDKANAL: comms_channel_id={$preferredChannel['comms_channel_id']} ({$preferredChannel['sender_identifier']})\n\n";
        }

        // Onboarding-ID
        $system .= "ONBOARDING-ID: {$onboarding->id}\n"
            . "- Zum Abschließen: Setze auto_pilot_completed_at per Update auf das Onboarding.\n\n"
            . "DEIN ABLAUF (führe jeden Schritt sofort per Tool-Call aus):\n"
            . "1. tools.GET — lade alle benötigten Tools\n"
            . "2. CRM-Kontakt prüfen — falls keiner verknüpft: suchen/erstellen und verknüpfen\n"
            . "3. Extra-Fields laden — prüfen welche required (is_required=true) und leer sind\n"
            . "4. Kommunikations-Threads prüfen:\n"
            . "   → WENN threads_summary LEER ist (keine Threads): Überspringe, gehe zu Schritt 7.\n"
            . "   → WENN threads_summary Einträge hat: Lade Nachrichten und prüfe auf neue verwertbare Infos.\n"
            . "5. WENN neue Infos in Nachrichten gefunden → SOFORT per core.extra_fields.PUT schreiben.\n"
            . "6. Extra-Fields erneut prüfen — welche Pflichtfelder sind JETZT noch leer?\n"
            . "7. ENTSCHEIDUNG:\n"
            . "   → Alle Pflichtfelder gefüllt? → auto_pilot_completed_at setzen. FERTIG.\n"
            . "   → Pflichtfelder fehlen, KEIN Thread? → Nachricht senden, fehlende Infos anfordern.\n"
            . "   → Pflichtfelder fehlen, Thread vorhanden, neue Infos verarbeitet? → REPLY, restliche Infos nachfragen.\n"
            . "   → Pflichtfelder fehlen, Thread vorhanden, KEINE neuen Infos? → Nichts tun. FERTIG.\n\n"
            . "REPLY-WORKFLOW (bestehender Thread):\n"
            . "- Für Reply NUR diese Parameter: core.comms.email_messages.POST { \"thread_id\": <thread_id>, \"body\": \"Dein Text\" }\n"
            . "- 'to' und 'subject' werden AUTOMATISCH aus dem Thread abgeleitet.\n\n"
            . "NEUER THREAD (nur wenn threads_summary LEER ist):\n"
            . "- core.comms.email_messages.POST { \"comms_channel_id\": <Kanal>, \"to\": \"<email>\", \"subject\": \"<Betreff>\", \"body\": \"...\" }\n";

        // Daten als user message
        $data = [
            'onboarding_id' => $onboarding->id,
            'public_url' => $publicUrl,
            'source_position_title' => $onboarding->source_position_title,
            'crm_contacts' => $contactInfo,
            'extra_fields' => $extraFields,
            'threads_summary' => $threadsSummary,
        ];

        if ($preferredChannel) {
            $data['preferred_channel'] = $preferredChannel;
        }

        $user = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nBearbeite dieses Onboarding. Beginne SOFORT mit Tool-Calls."
            . "\nErster Schritt: tools.GET um die benötigten Tools zu laden."
            . "\nHINWEIS: Falls du eine Nachricht sendest — kurz und freundlich mit dem Formular-Link ({$publicUrl}). KEINE einzelnen Felder abfragen!";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }
}
