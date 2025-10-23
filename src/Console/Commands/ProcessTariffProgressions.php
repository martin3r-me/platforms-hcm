<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\TariffProgressionService;

class ProcessTariffProgressions extends Command
{
    protected $signature = 'hcm:process-tariff-progressions 
                            {--date= : Process progressions for specific date (Y-m-d)}
                            {--dry-run : Show what would be processed without making changes}';

    protected $description = 'Process tariff level progressions for all contracts';

    public function handle(TariffProgressionService $progressionService): int
    {
        $date = $this->option('date');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        $this->info('Processing tariff progressions...');

        if ($dryRun) {
            $contracts = $progressionService->getContractsDueForProgression($date);
            
            $this->info("Found {$contracts->count()} contracts due for progression:");
            
            foreach ($contracts as $contract) {
                $this->line("- Contract {$contract->id} (Employee: {$contract->employee->employee_number})");
                $this->line("  Current: {$contract->tariffGroup->code} - {$contract->tariffLevel->code}");
                $this->line("  Next progression due: {$contract->next_tariff_level_date}");
            }
            
            return 0;
        }

        $results = $progressionService->processProgressions($date);

        $this->info("Processing completed:");
        $this->line("- Contracts processed: {$results['processed']}");
        $this->line("- Progressions applied: {$results['progressed']}");

        if (!empty($results['errors'])) {
            $this->warn("Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->line("- Contract {$error['contract_id']}: {$error['error']}");
            }
        }

        return 0;
    }
}
