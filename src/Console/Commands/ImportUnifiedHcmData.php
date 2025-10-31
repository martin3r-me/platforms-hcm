<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\UnifiedImportService;

class ImportUnifiedHcmData extends Command
{
    protected $signature = 'hcm:import-unified {employer_id} {csv_path} {--dry-run}';

    protected $description = 'Importiert Mitarbeiter-, Vertrags- und Lookup-Daten aus einer einheitlichen HCM-CSV (UTF-8, ;)';

    public function handle(): int
    {
        $employerId = (int) $this->argument('employer_id');
        $csvPath = (string) $this->argument('csv_path');
        $dry = (bool) $this->option('dry-run');

        if (!is_file($csvPath)) {
            $this->error("CSV nicht gefunden: {$csvPath}");
            return self::FAILURE;
        }

        $this->info(($dry ? 'DRY-RUN ' : '') . "Unified Import startet: Employer {$employerId}");
        $service = new UnifiedImportService($employerId);
        $stats = $service->run($csvPath, $dry);

        $this->table(['metric','value'], [
            ['rows', $stats['rows']],
            ['employees_created', $stats['employees_created']],
            ['employees_updated', $stats['employees_updated']],
            ['contracts_created', $stats['contracts_created']],
            ['contracts_updated', $stats['contracts_updated']],
            ['contacts_created', $stats['contacts_created']],
            ['contacts_updated', $stats['contacts_updated']],
            ['cost_centers_created', $stats['cost_centers_created']],
            ['titles_linked', $stats['titles_linked']],
            ['activities_linked', $stats['activities_linked']],
            ['lookups_created', $stats['lookups_created']],
            ['errors', count($stats['errors'])],
        ]);

        if (!empty($stats['samples'])) {
            $this->info('Samples:');
            foreach ($stats['samples'] as $s) {
                $this->line('- ' . json_encode($s, JSON_UNESCAPED_UNICODE));
            }
        }

        if (!empty($stats['errors'])) {
            $this->warn('Fehler:');
            foreach ($stats['errors'] as $e) {
                $this->line('- ' . $e);
            }
        }

        $this->info('Fertig.');
        return self::SUCCESS;
    }
}


