<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Platform\Hcm\Models\HcmOnboarding;

class DispatchEnrichInboxOnboardings extends Command
{
    protected $signature = 'hcm:dispatch-enrich-inbox-onboardings';

    protected $description = 'Holt alle Inbox-Onboardings (enrichment_status IS NULL) und übergibt sie einzeln an hcm:enrich-inbox-onboardings.';

    public function handle(): int
    {
        $onboardings = HcmOnboarding::query()
            ->whereNull('enrichment_status')
            ->orderBy('created_at', 'asc')
            ->pluck('id');

        if ($onboardings->isEmpty()) {
            $this->info('Keine offenen Inbox-Onboardings gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Verarbeite {$onboardings->count()} Inbox-Onboarding(s)...");

        foreach ($onboardings as $onboardingId) {
            $this->line("→ Onboarding #{$onboardingId}");

            Artisan::call('hcm:enrich-inbox-onboardings', [
                '--onboarding-id' => $onboardingId,
                '--limit' => 1,
            ]);

            $output = trim(Artisan::output());
            if ($output !== '') {
                foreach (explode("\n", $output) as $line) {
                    $this->line("  {$line}");
                }
            }
        }

        $this->info("Fertig. {$onboardings->count()} Onboarding(s) verarbeitet.");
        return Command::SUCCESS;
    }
}
