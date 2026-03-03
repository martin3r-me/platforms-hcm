<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Crm\Models\CommsEmailThread;
use Platform\Crm\Models\CommsWhatsAppThread;
use Platform\Hcm\Models\HcmAutoPilotLog;
use Platform\Hcm\Models\HcmOnboarding;

class EnrichInboxOnboardings extends Command
{
    protected $signature = 'hcm:enrich-inbox-onboardings
        {--limit=10 : Maximale Anzahl Onboardings pro Run}
        {--onboarding-id= : Optional: einzelnes Onboarding bearbeiten}
        {--dry-run : Zeigt nur, was bearbeitet würde}
        {--max-iterations=20 : Max. Tool-Loop Iterationen pro Onboarding}
        {--max-output-tokens=2000 : Max. Output Tokens pro LLM Call}';

    protected $description = 'Enrichment-Pipeline: Extrahiert Daten aus WhatsApp/Email-Threads und Anhängen per LLM in Extra-Felder und CRM-Kontakt (Onboarding).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, min(100, (int) $this->option('limit')));
        $onboardingId = $this->option('onboarding-id');
        $onboardingId = is_numeric($onboardingId) ? (int) $onboardingId : null;
        $maxIterations = max(1, min(200, (int) $this->option('max-iterations')));
        $maxOutputTokens = max(64, min(200000, (int) $this->option('max-output-tokens')));

        $lockKey = $onboardingId
            ? "hcm:enrich-inbox-onboarding:{$onboardingId}"
            : 'hcm:enrich-inbox-onboardings';
        $lock = Cache::lock($lockKey, 3600);
        if (! $lock->get()) {
            $this->warn('Läuft bereits (Lock aktiv).');
            return Command::SUCCESS;
        }

        try {
            if ($dryRun) {
                $this->warn('DRY-RUN — es werden keine Daten geändert.');
            }

            $runner = AiToolLoopRunner::make();
            $processed = 0;
            $seenIds = [];
            $originalAuthUser = Auth::user();

            while ($processed < $limit) {
                $onboarding = $this->nextOnboarding($onboardingId, $seenIds);
                if (! $onboarding) {
                    if ($processed === 0) {
                        $this->info('Keine offenen Inbox-Onboardings gefunden.');
                    }
                    break;
                }

                $seenIds[] = (int) $onboarding->id;
                $processed++;

                $admin = $this->findTeamAdmin($onboarding->team);
                if (! $admin) {
                    $this->line("Onboarding #{$onboarding->id}: übersprungen (kein Team-Admin).");
                    continue;
                }

                $model = $this->determineModel();
                $contactInfo = $this->loadContactInfo($onboarding);
                $extraFields = $this->loadExtraFields($onboarding);
                $whatsappThreads = $this->loadWhatsAppThreads($onboarding);
                $emailThreads = $this->loadEmailThreads($onboarding);
                $fileReferences = $this->loadFileReferences($onboarding, $whatsappThreads, $emailThreads);

                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("Onboarding #{$onboarding->id} → Admin: {$admin->name}");
                $this->line("Team: " . ($onboarding->team?->name ?? '—'));
                $this->line("Model: {$model}");
                $this->line("Kontakte: " . count($contactInfo));
                $this->line("Extra-Fields: " . count($extraFields));
                $this->line("WhatsApp-Threads: " . count($whatsappThreads));
                $this->line("Email-Threads: " . count($emailThreads));
                $this->line("Datei-Referenzen: " . count($fileReferences));

                if ($dryRun) {
                    continue;
                }

                // Mark as processing in cache (not in DB — stays in inbox until done)
                Cache::put("onboarding-enrichment:processing:{$onboarding->id}", true, 600);

                $this->impersonateForTask($admin, $onboarding->team);
                $toolContext = new ToolContext($admin, $onboarding->team, [
                    'context_model' => get_class($onboarding),
                    'context_model_id' => $onboarding->id,
                ]);

                $preloadTools = [
                    'core.extra_fields.GET', 'core.extra_fields.PUT',
                    'core.context.files.GET', 'core.context.files.content.GET',
                    'crm.contacts.GET', 'crm.contacts.POST', 'crm.contacts.PUT',
                    'crm.phone_numbers.POST', 'crm.email_addresses.POST',
                    'crm.lookups.GET', 'crm.lookup.GET',
                    'crm.postal_addresses.POST',
                ];

                $messages = $this->buildMessages(
                    $onboarding, $contactInfo, $extraFields,
                    $whatsappThreads, $emailThreads, $fileReferences
                );

                $this->logEnrichment($onboarding, 'run_started', 'Enrichment-Run gestartet', [
                    'preload_tools' => $preloadTools,
                ]);

                try {
                    $result = $runner->run($messages, $model, $toolContext, [
                        'max_iterations' => $maxIterations,
                        'max_output_tokens' => $maxOutputTokens,
                        'include_web_search' => false,
                        'reasoning' => ['effort' => 'medium'],
                        'preload_tools' => $preloadTools,
                        'skip_discovery_tools' => true,
                        'on_iteration' => function (int $iter, array $toolNames, int $textLen) {
                            $this->line("  Iter {$iter}: " . (empty($toolNames) ? '(keine Tools)' : implode(', ', $toolNames)));
                        },
                        'on_tool_result' => function (string $tool, array $args, array $result) {
                            $ok = $result['ok'] ?? false;
                            if (!$ok) {
                                $errMsg = $result['error']['message'] ?? $result['error']['code'] ?? 'unknown';
                                $this->warn("    ⚠ {$tool} FEHLER: {$errMsg}");
                                $this->warn("      Args: " . json_encode($args, JSON_UNESCAPED_UNICODE));
                            }
                        },
                    ]);

                    $iterations = (int) ($result['iterations'] ?? 0);
                    $allToolCallNames = $result['all_tool_call_names'] ?? [];

                    $this->logEnrichment($onboarding, 'run_completed', "Enrichment abgeschlossen: {$iterations} Iterationen", [
                        'iterations' => $iterations,
                        'all_tool_calls' => $allToolCallNames,
                    ]);

                    $this->line("  Iterationen: {$iterations} | Tools: " . (empty($allToolCallNames) ? '(keine)' : implode(', ', $allToolCallNames)));

                    // Deterministic post-LLM step: ensure contact is linked
                    $onboarding->refresh();
                    $onboarding->load('crmContactLinks');

                    if ($onboarding->crmContactLinks->isEmpty()) {
                        $this->tryAutoLinkContact($onboarding, $admin);
                        $onboarding->load('crmContactLinks');
                    }

                    if ($onboarding->crmContactLinks->isNotEmpty()) {
                        $onboarding->update(['enrichment_status' => 'enriched']);
                        $this->info("  Enrichment abgeschlossen.");
                    } else {
                        $onboarding->update(['enrichment_status' => 'no_contact']);
                        $this->warn("  Enrichment durchgelaufen, aber kein CRM-Kontakt verknüpft — manuelle Prüfung nötig.");
                        $this->logEnrichment($onboarding, 'no_contact', 'Enrichment abgeschlossen, aber kein CRM-Kontakt verknüpft. Manuelle Prüfung erforderlich.');
                    }
                    Cache::forget("onboarding-enrichment:processing:{$onboarding->id}");
                } catch (\Throwable $e) {
                    $this->logEnrichment($onboarding, 'error', 'LLM-Fehler: ' . $e->getMessage());
                    $this->error("  Fehler: " . $e->getMessage());
                    $onboarding->update(['enrichment_status' => 'failed']);
                    Cache::forget("onboarding-enrichment:processing:{$onboarding->id}");
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
            ->whereNull('enrichment_status');

        if ($onboardingId) {
            $query->where('id', $onboardingId);
        }

        if (! empty($excludeIds)) {
            $query->whereNotIn('id', array_map('intval', $excludeIds));
        }

        return $query->orderBy('created_at', 'asc')->first();
    }

    private function findTeamAdmin(?Team $team): ?User
    {
        if (! $team) {
            return null;
        }

        return $team->users()->wherePivot('role', 'admin')->orderBy('id')->first()
            ?? $team->users()->wherePivot('role', 'owner')->orderBy('id')->first()
            ?? $team->users()->orderBy('id')->first();
    }

    private function determineModel(): string
    {
        try {
            $provider = CoreAiProvider::where('key', 'openai')->where('is_active', true)->with('defaultModel')->first();
            $fallback = $provider?->defaultModel?->model_id;
            if (is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        } catch (\Throwable $e) {}

        return 'gpt-5.2';
    }

    private function impersonateForTask(User $user, ?Team $team): void
    {
        Auth::setUser($user);

        if ($team) {
            $user->current_team_id = (int) $team->id;
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
                if (! $c) { return null; }
                return [
                    'contact_id' => $c->id,
                    'full_name' => $c->full_name,
                    'emails' => $c->emailAddresses?->map(fn ($e) => [
                        'email' => $e->email_address,
                        'is_primary' => (bool) $e->is_primary,
                    ])->values()->toArray() ?? [],
                    'phones' => $c->phoneNumbers?->map(fn ($p) => [
                        'number' => $p->international,
                        'is_primary' => (bool) $p->is_primary,
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

    private function loadWhatsAppThreads(HcmOnboarding $onboarding): array
    {
        try {
            if (! class_exists(CommsWhatsAppThread::class)) {
                return [];
            }

            $morphClass = $onboarding->getMorphClass();
            $fullClass = get_class($onboarding);

            $threads = CommsWhatsAppThread::query()
                ->where(function ($q) use ($morphClass, $fullClass, $onboarding) {
                    $q->where(function ($q2) use ($morphClass, $onboarding) {
                        $q2->where('context_model', $morphClass)
                            ->where('context_model_id', $onboarding->id);
                    })->orWhere(function ($q2) use ($fullClass, $onboarding) {
                        $q2->where('context_model', $fullClass)
                            ->where('context_model_id', $onboarding->id);
                    });
                })
                ->with(['messages' => fn ($q) => $q->orderBy('created_at', 'asc')])
                ->orderByDesc(DB::raw('COALESCE(last_inbound_at, last_outbound_at, updated_at)'))
                ->limit(10)
                ->get();

            // If none found, try matching by contact phone numbers
            if ($threads->isEmpty()) {
                $phones = $this->getContactPhoneNumbers($onboarding);
                if (! empty($phones)) {
                    $threads = CommsWhatsAppThread::query()
                        ->where(function ($q) use ($phones) {
                            foreach ($phones as $phone) {
                                $digits = preg_replace('/[^0-9]/', '', $phone);
                                $q->orWhereRaw("REPLACE(REPLACE(remote_phone_number, '+', ''), ' ', '') LIKE ?", ['%' . $digits]);
                            }
                        })
                        ->with(['messages' => fn ($q) => $q->orderBy('created_at', 'asc')])
                        ->orderByDesc(DB::raw('COALESCE(last_inbound_at, last_outbound_at, updated_at)'))
                        ->limit(10)
                        ->get();

                    foreach ($threads as $t) {
                        if (! $t->context_model) {
                            $t->update([
                                'context_model' => $morphClass,
                                'context_model_id' => $onboarding->id,
                            ]);
                        }
                    }
                }
            }

            return $threads->map(fn ($t) => [
                'thread_id' => $t->id,
                'remote_phone_number' => $t->remote_phone_number,
                'last_inbound_at' => $t->last_inbound_at?->toIso8601String(),
                'last_outbound_at' => $t->last_outbound_at?->toIso8601String(),
                'messages' => $t->messages->map(function ($m) {
                    $msg = [
                        'direction' => $m->direction,
                        'body' => $m->body,
                        'message_type' => $m->message_type,
                        'sent_at' => $m->sent_at?->toIso8601String(),
                        'created_at' => $m->created_at?->toIso8601String(),
                    ];

                    if ($m->message_type && $m->message_type !== 'text' && $m->message_type !== 'template') {
                        $fileRefs = [];
                        foreach ($m->getOrderedFileReferences() as $ref) {
                            if (!$ref->contextFile) { continue; }
                            $entry = [
                                'context_file_id' => $ref->contextFile->id,
                                'title' => $ref->contextFile->original_name ?? $ref->contextFile->title ?? '(Anhang)',
                                'mime_type' => $ref->contextFile->mime_type ?? null,
                            ];
                            if ($ref->caption) {
                                $entry['caption'] = $ref->caption;
                            }
                            $fileRefs[] = $entry;
                        }
                        if (!empty($fileRefs)) {
                            $msg['attachments'] = $fileRefs;
                        }
                    }

                    return $msg;
                })->toArray(),
            ])->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getContactPhoneNumbers(HcmOnboarding $onboarding): array
    {
        try {
            $onboarding->loadMissing(['crmContactLinks.contact.phoneNumbers']);
            $phones = [];
            foreach ($onboarding->crmContactLinks as $link) {
                foreach ($link->contact?->phoneNumbers ?? [] as $p) {
                    if ($p->international) {
                        $phones[] = $p->international;
                    } elseif ($p->raw_input) {
                        $phones[] = $p->raw_input;
                    }
                }
            }
            return $phones;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadEmailThreads(HcmOnboarding $onboarding): array
    {
        try {
            if (! class_exists(CommsEmailThread::class)) {
                return [];
            }

            $morphClass = $onboarding->getMorphClass();
            $fullClass = get_class($onboarding);

            $threads = CommsEmailThread::query()
                ->where(function ($q) use ($morphClass, $fullClass, $onboarding) {
                    $q->where(function ($q2) use ($morphClass, $onboarding) {
                        $q2->where('context_model', $morphClass)
                            ->where('context_model_id', $onboarding->id);
                    })->orWhere(function ($q2) use ($fullClass, $onboarding) {
                        $q2->where('context_model', $fullClass)
                            ->where('context_model_id', $onboarding->id);
                    });
                })
                ->with([
                    'inboundMails' => fn ($q) => $q->orderBy('received_at', 'asc'),
                    'outboundMails' => fn ($q) => $q->orderBy('created_at', 'asc'),
                ])
                ->orderByDesc(DB::raw('COALESCE(last_inbound_at, last_outbound_at, updated_at)'))
                ->limit(10)
                ->get();

            return $threads->map(fn ($t) => [
                'thread_id' => $t->id,
                'subject' => $t->subject,
                'counterpart' => $t->last_inbound_from_address ?: $t->last_outbound_to_address,
                'last_inbound_at' => $t->last_inbound_at?->toIso8601String(),
                'last_outbound_at' => $t->last_outbound_at?->toIso8601String(),
                'inbound_messages' => $t->inboundMails->map(fn ($m) => [
                    'from' => $m->from,
                    'subject' => $m->subject,
                    'text_body' => $m->text_body,
                    'received_at' => $m->received_at?->toIso8601String(),
                ])->toArray(),
                'outbound_messages' => $t->outboundMails->map(fn ($m) => [
                    'to' => $m->to,
                    'subject' => $m->subject,
                    'text_body' => $m->text_body ?? null,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->toArray(),
            ])->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadFileReferences(HcmOnboarding $onboarding, array $whatsappThreads, array $emailThreads): array
    {
        $refs = [];

        try {
            // WhatsApp message attachments
            if (class_exists(\Platform\Crm\Models\CommsWhatsAppMessage::class)) {
                $threadIds = array_column($whatsappThreads, 'thread_id');
                if (! empty($threadIds)) {
                    $messages = \Platform\Crm\Models\CommsWhatsAppMessage::whereIn('comms_whatsapp_thread_id', $threadIds)->get();
                    foreach ($messages as $msg) {
                        foreach ($msg->getOrderedFileReferences() as $ref) {
                            if (! $ref->contextFile) { continue; }
                            $refs[] = [
                                'source' => 'whatsapp',
                                'context_file_id' => $ref->contextFile->id,
                                'title' => $ref->contextFile->original_name ?? $ref->contextFile->title ?? '(unbekannt)',
                                'mime_type' => $ref->contextFile->mime_type ?? null,
                            ];
                        }
                    }
                }
            }

            // Email inbound attachments
            if (class_exists(\Platform\Crm\Models\CommsEmailInboundMail::class)) {
                $threadIds = array_column($emailThreads, 'thread_id');
                if (! empty($threadIds)) {
                    $mails = \Platform\Crm\Models\CommsEmailInboundMail::whereIn('thread_id', $threadIds)->get();
                    foreach ($mails as $mail) {
                        foreach ($mail->getOrderedFileReferences() as $ref) {
                            if (! $ref->contextFile) { continue; }
                            $refs[] = [
                                'source' => 'email',
                                'context_file_id' => $ref->contextFile->id,
                                'title' => $ref->contextFile->original_name ?? $ref->contextFile->title ?? '(unbekannt)',
                                'mime_type' => $ref->contextFile->mime_type ?? null,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore — file loading should not break the run
        }

        return $refs;
    }

    private function tryAutoLinkContact(HcmOnboarding $onboarding, User $admin): void
    {
        try {
            $onboardingMorphClass = $onboarding->getMorphClass();

            // Strategy 1: Find recently created contacts by this admin
            $cutoff = Carbon::now()->subMinutes(10);
            $recentContacts = \Platform\Crm\Models\CrmContact::where('team_id', $onboarding->team_id)
                ->where('created_by_user_id', $admin->id)
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            $unlinked = $recentContacts->filter(function ($contact) use ($onboarding, $onboardingMorphClass) {
                return !\Platform\Crm\Models\CrmContactLink::where('contact_id', $contact->id)
                    ->where('linkable_id', $onboarding->id)
                    ->where('linkable_type', $onboardingMorphClass)
                    ->exists();
            });

            if ($unlinked->isNotEmpty()) {
                $contact = $unlinked->first();
                $onboarding->crmContactLinks()->create([
                    'contact_id' => $contact->id,
                    'team_id' => $onboarding->team_id,
                    'created_by_user_id' => $admin->id,
                ]);
                $this->info("  Auto-Link: Kontakt #{$contact->id} ({$contact->full_name}) verknüpft (kürzlich erstellt).");
                $this->logEnrichment($onboarding, 'auto_linked', "CRM-Kontakt #{$contact->id} ({$contact->full_name}) automatisch verknüpft (kürzlich erstellt).");
                return;
            }

            // Strategy 2: Search existing CRM contacts by name from extra fields
            $nameFromFields = $this->extractNameFromExtraFields($onboarding);
            if ($nameFromFields) {
                $query = \Platform\Crm\Models\CrmContact::where('team_id', $onboarding->team_id)
                    ->where('is_active', true);

                if ($nameFromFields['last_name']) {
                    $query->where('last_name', $nameFromFields['last_name']);
                }
                if ($nameFromFields['first_name']) {
                    $query->where('first_name', $nameFromFields['first_name']);
                }

                $matches = $query->limit(2)->get();

                if ($matches->count() === 1) {
                    $contact = $matches->first();

                    $alreadyLinked = \Platform\Crm\Models\CrmContactLink::where('contact_id', $contact->id)
                        ->where('linkable_id', $onboarding->id)
                        ->where('linkable_type', $onboardingMorphClass)
                        ->exists();

                    if (! $alreadyLinked) {
                        $onboarding->crmContactLinks()->create([
                            'contact_id' => $contact->id,
                            'team_id' => $onboarding->team_id,
                            'created_by_user_id' => $admin->id,
                        ]);
                        $this->info("  Auto-Link: Kontakt #{$contact->id} ({$contact->full_name}) verknüpft (Name-Match aus Extra-Fields).");
                        $this->logEnrichment($onboarding, 'auto_linked', "CRM-Kontakt #{$contact->id} ({$contact->full_name}) automatisch verknüpft (Name-Match).");
                        return;
                    }
                } elseif ($matches->count() > 1) {
                    $this->line("  Auto-Link: Mehrere Kontakte gefunden für '{$nameFromFields['first_name']} {$nameFromFields['last_name']}' — übersprungen (mehrdeutig).");
                }
            }

            $this->line("  Auto-Link: Kein passender Kontakt gefunden.");
        } catch (\Throwable $e) {
            $this->warn("  Auto-Link Fehler: " . $e->getMessage());
        }
    }

    private function extractNameFromExtraFields(HcmOnboarding $onboarding): ?array
    {
        try {
            $fields = $onboarding->getExtraFieldsWithLabels();
            $firstName = null;
            $lastName = null;

            foreach ($fields as $field) {
                $key = strtolower($field['key'] ?? '');
                $value = $field['value'] ?? null;
                if (empty($value) || !is_string($value)) continue;

                if (in_array($key, ['first_name', 'vorname', 'firstname'])) {
                    $firstName = trim($value);
                } elseif (in_array($key, ['last_name', 'nachname', 'lastname', 'name'])) {
                    $lastName = trim($value);
                }
            }

            if (! $lastName) {
                return null;
            }

            return ['first_name' => $firstName, 'last_name' => $lastName];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function logEnrichment(HcmOnboarding $onboarding, string $type, string $summary, ?array $details = null): void
    {
        try {
            HcmAutoPilotLog::create([
                'hcm_onboarding_id' => $onboarding->id,
                'type' => $type,
                'summary' => '[Enrichment] ' . $summary,
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            // ignore — logging should never break the run
        }
    }

    private function buildMessages(
        HcmOnboarding $onboarding,
        array $contactInfo,
        array $extraFields,
        array $whatsappThreads,
        array $emailThreads,
        array $fileReferences,
    ): array {
        $system = "Du bist ein Datenextraktions-Agent für ein Onboarding-System (HCM).\n"
            . "Deine Aufgabe: Analysiere alle bereitgestellten Daten (Nachrichten, Anhänge, Kontaktinfos) "
            . "und extrahiere alle verwertbaren Informationen für das Onboarding eines neuen Mitarbeiters.\n\n"
            . "REGELN:\n"
            . "- Schreibe extrahierte Daten in die Extra-Felder des Onboardings (core.extra_fields.PUT).\n"
            . "- Aktualisiere den CRM-Kontakt (Name, Telefon, Email) falls du bessere/vollständigere Daten findest (crm.contacts.PUT).\n"
            . "- Falls kein CRM-Kontakt verknüpft ist, erstelle/suche einen und verknüpfe ihn.\n"
            . "- Du arbeitest autonom per Tool-Calls. Schreibe keine Reports oder Zusammenfassungen.\n"
            . "- Antworte auf Deutsch.\n\n"
            . "VERBOTEN:\n"
            . "- Sende KEINE Nachrichten — NIEMALS. Kein Email, kein WhatsApp, nichts.\n"
            . "- Rufe NICHT tools.GET auf — alle benötigten Tools sind bereits geladen.\n"
            . "- Lade KEINE zusätzlichen Tools nach. Du hast alles was du brauchst.\n\n"
            . "VERFÜGBARE TOOLS (bereits geladen):\n"
            . "- core.extra_fields.GET — Extra-Field-Definitionen und Werte laden\n"
            . "- core.extra_fields.PUT — Extra-Field-Werte schreiben (auch für file-Felder: Wert = context_file_id)\n"
            . "- core.context.files.GET — Dateien am Onboarding-Objekt auflisten\n"
            . "- core.context.files.content.GET — Datei-Inhalt lesen (Text, PDF-Text, Bilder als URL)\n"
            . "- crm.contacts.GET — CRM-Kontakt laden\n"
            . "- crm.contacts.POST — Neuen CRM-Kontakt erstellen (first_name, last_name, contact_status_id)\n"
            . "- crm.contacts.PUT — CRM-Kontakt aktualisieren (first_name, last_name, salutation_id, academic_title_id, gender_id, birth_date)\n"
            . "- crm.phone_numbers.POST — Telefonnummer an CRM-Kontakt anlegen (contact_id, raw_input, phone_type_id, is_primary)\n"
            . "- crm.email_addresses.POST — Email-Adresse an CRM-Kontakt anlegen (contact_id, email_address, email_type_id, is_primary)\n"
            . "- crm.lookups.GET — Lookup-Typen auflisten (z.B. salutation, academic_title, gender, country, address_type, phone_type, email_type)\n"
            . "- crm.lookup.GET — Einzelnen Lookup mit allen Werten laden (IDs für Anrede, Titel, Geschlecht, Telefon-Typ, Email-Typ etc.)\n"
            . "- crm.postal_addresses.POST — Postadresse an CRM-Kontakt anlegen (street, zip, city, country_id, address_type_id)\n\n"
            . "ABLAUF:\n"
            . "1. Lade Extra-Field-Definitionen per core.extra_fields.GET um zu sehen was erwartet wird.\n"
            . "2. Lade Lookup-IDs per crm.lookups.GET, dann crm.lookup.GET für: salutation, academic_title, gender, contact_status.\n"
            . "   Diese IDs brauchst du für Anrede, Titel, Geschlecht und Kontakt-Status (ACTIVE).\n"
            . "3. Falls crm_contacts LEER ist → SOFORT Kontakt erstellen und verknüpfen. Ohne Kontakt können die folgenden Schritte nicht funktionieren.\n"
            . "4. Analysiere die bereitgestellten Nachrichten und Kontaktinfos.\n"
            . "5. Falls Datei-Referenzen vorhanden: Lies deren Inhalt per core.context.files.content.GET und extrahiere verwertbare Daten.\n"
            . "6. Schreibe alle extrahierbaren Werte per core.extra_fields.PUT.\n"
            . "   - Format: {\"fields\": {\"feldkey\": \"wert\", ...}} — nutze die Keys aus core.extra_fields.GET (Schritt 1).\n"
            . "   - Sende NUR Felder mit einem tatsächlichen Wert. NIEMALS null oder \"\" mitsenden!\n"
            . "   - null oder \"\" LÖSCHT den bestehenden Wert — das ist fast nie gewollt.\n"
            . "   - Wenn du keinen Wert für ein Feld hast, lasse es komplett weg.\n"
            . "   - Für file-Felder: setze den Wert auf die context_file_id (Integer) der passenden Datei.\n"
            . "7. Aktualisiere den CRM-Kontakt per crm.contacts.PUT:\n"
            . "   - Setze salutation_id (Anrede: Herr/Frau), academic_title_id (Titel: Dr., Prof. etc.), gender_id wenn erkennbar.\n"
            . "   - Setze birth_date (Format: YYYY-MM-DD) wenn verfügbar.\n"
            . "   - Aktualisiere first_name, last_name falls vollständiger als bisherige Daten.\n"
            . "8. Falls Telefonnummer gefunden: Lade phone_type per crm.lookup.GET, dann crm.phone_numbers.POST mit contact_id, raw_input, phone_type_id.\n"
            . "9. Email-Adresse — WICHTIG:\n"
            . "   a) Suche die persönliche Email-Adresse. Bevorzugte Quellen (in dieser Reihenfolge):\n"
            . "      1. Anhänge/Dokumente (per core.context.files.content.GET lesen).\n"
            . "      2. Email-Body (text_body) — im Fließtext oder in der Signatur.\n"
            . "      3. Absender-Adresse (from) — NUR als letzter Fallback und NUR wenn es eine persönliche Adresse ist.\n"
            . "   b) NIEMALS Portal-/System-Adressen verwenden (z.B. noreply@, notification@, *@jobs.*, *@portal.*).\n"
            . "   c) Lege genau EINE Email-Adresse an per crm.email_addresses.POST (contact_id, email_address, email_type_id, is_primary=true).\n"
            . "10. Postadresse: Falls eine Adresse erkennbar ist (aus Dokumenten, Email-Body, Anhängen),\n"
            . "   lade per crm.lookups.GET/crm.lookup.GET die IDs für country und address_type,\n"
            . "   dann lege die Adresse per crm.postal_addresses.POST am Kontakt an.\n"
            . "11. Falls kein Kontakt verknüpft (crm_contacts ist leer) — DIESER SCHRITT HAT HÖCHSTE PRIORITÄT:\n"
            . "    a) Suche per crm.contacts.GET ob der Kontakt bereits existiert.\n"
            . "    b) Falls nicht gefunden: Erstelle per crm.contacts.POST einen neuen Kontakt (first_name, last_name, contact_status_id — lade status_id per crm.lookup.GET für 'contact_status', nutze 'ACTIVE').\n"
            . "    c) DANN: Lege Email, Telefon und Adresse am neu erstellten Kontakt an (Schritte 8-10).\n"
            . "    WICHTIG: Schritt 11 hat HÖCHSTE PRIORITÄT — führe ihn VOR den Detail-Schritten (8-10) aus wenn kein Kontakt verknüpft ist (siehe Schritt 3).\n\n"
            . "WICHTIG:\n"
            . "- Extrahiere ALLES was verwertbar ist: Name, Geburtsdatum, Adresse, Qualifikationen, "
            . "Berufserfahrung, Verfügbarkeit, Steuer-ID, Sozialversicherungsnummer, Bankverbindung, etc.\n"
            . "- Wenn du Infos in den Nachrichten findest die zu einem Extra-Feld passen, schreibe sie.\n"
            . "- Lies Datei-Anhänge (Dokumente, Unterlagen) per core.context.files.content.GET — sie enthalten oft die wichtigsten Infos.\n"
            . "- Beginne SOFORT mit Tool-Calls.\n\n"
            . "KRITISCH — KONTAKT VERKNÜPFEN:\n"
            . "- Am Ende MUSS ein CRM-Kontakt mit dem Onboarding verknüpft sein.\n"
            . "- Prüfe am Anfang ob crm_contacts leer ist. Falls ja:\n"
            . "  1. crm.contacts.POST → neuen Kontakt erstellen\n"
            . "  2. Der Kontakt wird automatisch verknüpft.\n"
            . "- Ohne Verknüpfung gilt die Enrichment als gescheitert.\n";

        $data = [
            'onboarding_id' => $onboarding->id,
            'team_id' => $onboarding->team_id,
            'notes' => $onboarding->notes,
            'source_position_title' => $onboarding->source_position_title,
            'crm_contacts' => $contactInfo,
            'extra_fields' => $extraFields,
        ];

        if (! empty($whatsappThreads)) {
            $data['whatsapp_threads'] = $whatsappThreads;
        }

        if (! empty($emailThreads)) {
            $data['email_threads'] = $emailThreads;
        }

        if (! empty($fileReferences)) {
            $data['file_references'] = $fileReferences;
        }

        $user = "Onboarding (JSON):\n"
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "Extrahiere alle verwertbaren Informationen und schreibe sie in die passenden Felder. "
            . "Alle Tools sind bereits geladen — beginne SOFORT mit core.extra_fields.GET.";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }
}
