<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Crm\Models\CommsChannel;
use Platform\Crm\Models\CrmPhoneNumber;
use Platform\Crm\Services\Comms\WhatsAppMetaService;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Models\HcmInterviewBooking;

class SendInterviewReminders extends Command
{
    protected $signature = 'hcm:send-interview-reminders';

    protected $description = 'Sendet WhatsApp-Erinnerungen für anstehende Interview-Termine.';

    public function handle(): int
    {
        if (!class_exists(\Platform\Integrations\Models\IntegrationsWhatsAppTemplate::class)) {
            $this->warn('WhatsApp-Integrations-Modul nicht verfügbar.');
            return Command::SUCCESS;
        }

        $interviews = HcmInterview::query()
            ->whereNotNull('reminder_wa_template_id')
            ->whereNotNull('reminder_hours_before')
            ->whereIn('status', ['planned', 'confirmed'])
            ->where('starts_at', '>', now())
            ->whereRaw('starts_at <= DATE_ADD(NOW(), INTERVAL reminder_hours_before HOUR)')
            ->get();

        if ($interviews->isEmpty()) {
            $this->info('Keine fälligen Erinnerungen.');
            return Command::SUCCESS;
        }

        $this->info("Verarbeite {$interviews->count()} Interview(s) mit fälligen Erinnerungen...");

        $sent = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($interviews as $interview) {
            $bookings = $interview->bookings()
                ->whereNull('reminder_sent_at')
                ->where('status', '!=', 'cancelled')
                ->with(['onboarding.crmContactLinks.contact.phoneNumbers'])
                ->get();

            if ($bookings->isEmpty()) {
                $this->line("  Interview #{$interview->id}: keine offenen Buchungen.");
                continue;
            }

            $template = \Platform\Integrations\Models\IntegrationsWhatsAppTemplate::find($interview->reminder_wa_template_id);
            if (!$template || $template->status !== 'APPROVED') {
                $this->warn("  Interview #{$interview->id}: Template nicht gefunden oder nicht APPROVED.");
                continue;
            }

            $channel = $this->resolveWhatsAppChannel($template);
            if (!$channel) {
                $this->warn("  Interview #{$interview->id}: Kein aktiver WhatsApp-Kanal gefunden.");
                continue;
            }

            foreach ($bookings as $booking) {
                $phoneNumber = $this->findPhoneNumber($booking);
                if (!$phoneNumber) {
                    $this->line("  Buchung #{$booking->id}: Keine Telefonnummer gefunden, übersprungen.");
                    $skipped++;
                    continue;
                }

                try {
                    $components = $interview->resolveTemplateComponents(
                        $template->components ?? [],
                        $booking,
                    );

                    $service = app(WhatsAppMetaService::class);
                    $message = $service->sendTemplate(
                        channel: $channel,
                        to: $phoneNumber->international,
                        templateName: $template->name,
                        components: $components,
                        languageCode: $template->language ?? 'de',
                    );

                    // Link thread to onboarding context
                    if ($message->thread && $booking->onboarding) {
                        $message->thread->addContext(
                            get_class($booking->onboarding),
                            $booking->onboarding->id,
                            'interview_reminder',
                        );
                    }

                    $booking->update(['reminder_sent_at' => now()]);
                    $sent++;
                    $this->line("  Buchung #{$booking->id}: Erinnerung gesendet an {$phoneNumber->international}");
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("  Buchung #{$booking->id}: Fehler: {$e->getMessage()}");
                }
            }
        }

        $this->info("Fertig. Gesendet: {$sent}, Übersprungen: {$skipped}, Fehler: {$errors}");

        return Command::SUCCESS;
    }

    private function resolveWhatsAppChannel(\Platform\Integrations\Models\IntegrationsWhatsAppTemplate $template): ?CommsChannel
    {
        $account = $template->whatsappAccount;
        if (!$account || !$account->active) {
            return null;
        }

        return CommsChannel::where('type', 'whatsapp')
            ->where('is_active', true)
            ->where('sender_identifier', $account->phone_number)
            ->first();
    }

    private function findPhoneNumber(HcmInterviewBooking $booking): ?CrmPhoneNumber
    {
        $onboarding = $booking->onboarding;
        if (!$onboarding) {
            return null;
        }

        foreach ($onboarding->crmContactLinks as $link) {
            $contact = $link->contact;
            if (!$contact) {
                continue;
            }

            $primary = $contact->phoneNumbers
                ->where('is_active', true)
                ->where('is_primary', true)
                ->whereNotNull('international')
                ->first();

            if ($primary) {
                return $primary;
            }

            $fallback = $contact->phoneNumbers
                ->where('is_active', true)
                ->whereNotNull('international')
                ->first();

            if ($fallback) {
                return $fallback;
            }
        }

        return null;
    }
}
