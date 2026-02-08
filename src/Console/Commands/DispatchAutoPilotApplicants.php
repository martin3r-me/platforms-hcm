<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Platform\Hcm\Models\HcmApplicant;

class DispatchAutoPilotApplicants extends Command
{
    protected $signature = 'hcm:dispatch-auto-pilot-applicants';

    protected $description = 'Holt alle AutoPilot-Bewerbungen und übergibt sie einzeln an hcm:process-auto-pilot-applicants.';

    public function handle(): int
    {
        $applicants = HcmApplicant::query()
            ->where('auto_pilot', true)
            ->whereNull('auto_pilot_completed_at')
            ->whereNotNull('owned_by_user_id')
            ->orderBy('updated_at', 'asc')
            ->pluck('id');

        if ($applicants->isEmpty()) {
            $this->info('Keine offenen AutoPilot-Bewerbungen gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Verarbeite {$applicants->count()} AutoPilot-Bewerbung(en)...");

        foreach ($applicants as $applicantId) {
            $this->line("→ Bewerbung #{$applicantId}");

            Artisan::call('hcm:process-auto-pilot-applicants', [
                '--applicant-id' => $applicantId,
                '--limit' => 1,
            ]);

            $output = trim(Artisan::output());
            if ($output !== '') {
                // Jede Zeile eingerückt ausgeben
                foreach (explode("\n", $output) as $line) {
                    $this->line("  {$line}");
                }
            }
        }

        $this->info("Fertig. {$applicants->count()} Bewerbung(en) verarbeitet.");
        return Command::SUCCESS;
    }
}
