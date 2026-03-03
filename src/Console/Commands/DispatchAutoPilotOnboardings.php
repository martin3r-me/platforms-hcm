<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Platform\Hcm\Models\HcmOnboarding;

class DispatchAutoPilotOnboardings extends Command
{
    protected $signature = 'hcm:dispatch-auto-pilot-onboardings';

    protected $description = 'Holt alle AutoPilot-Onboardings und übergibt sie einzeln an hcm:process-auto-pilot-onboardings.';

    public function handle(): int
    {
        $onboardings = HcmOnboarding::query()
            ->where('auto_pilot', true)
            ->whereNull('auto_pilot_completed_at')
            ->whereNotNull('owned_by_user_id')
            ->orderBy('updated_at', 'asc')
            ->pluck('id');

        if ($onboardings->isEmpty()) {
            $this->info('Keine offenen AutoPilot-Onboardings gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Verarbeite {$onboardings->count()} AutoPilot-Onboarding(s)...");

        foreach ($onboardings as $onboardingId) {
            $this->line("→ Onboarding #{$onboardingId}");

            Artisan::call('hcm:process-auto-pilot-onboardings', [
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
