<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\UnifiedImportService;

class ImportJobRadBenefits extends Command
{
    protected $signature = 'hcm:import-jobrad {employer_id} {csv_path} {--dry-run}';

    protected $description = 'Importiert JobRad-Leasingdaten aus einer CSV-Datei und ordnet sie Mitarbeitern zu';

    public function handle(): int
    {
        $employerId = (int) $this->argument('employer_id');
        $csvPath = (string) $this->argument('csv_path');
        $dryRun = (bool) $this->option('dry-run');

        if (!is_file($csvPath)) {
            $this->error("CSV nicht gefunden: {$csvPath}");
            return self::FAILURE;
        }

        $this->info(($dryRun ? 'DRY-RUN ' : '') . "JobRad-Import startet (Employer ID: {$employerId})");

        try {
            $service = new UnifiedImportService($employerId);
            $stats = $service->importJobRadBenefits($csvPath, $dryRun);
        } catch (\Throwable $e) {
            $this->error('Fehler beim Import: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->table(['Metrik', 'Wert'], [
            ['Zeilen verarbeitet', $stats['rows']],
            ['JobRad-Benefits erstellt', $stats['benefits_created']],
            ['JobRad-Benefits aktualisiert', $stats['benefits_updated']],
            ['Mitarbeiter nicht gefunden', count($stats['employees_not_found'])],
            ['Verträge nicht gefunden', count($stats['contracts_missing'])],
            ['Fehler', count($stats['errors'])],
        ]);

        if (!empty($stats['samples'])) {
            $this->info('Samples:');
            foreach ($stats['samples'] as $sample) {
                $this->line('  - ' . json_encode($sample, JSON_UNESCAPED_UNICODE));
            }
        }

        if (!empty($stats['employees_not_found'])) {
            $this->warn('Mitarbeiter nicht gefunden:');
            foreach ($stats['employees_not_found'] as $name) {
                $this->line('  - ' . $name);
            }
        }

        if (!empty($stats['contracts_missing'])) {
            $this->warn('Verträge nicht gefunden:');
            foreach ($stats['contracts_missing'] as $name) {
                $this->line('  - ' . $name);
            }
        }

        if (!empty($stats['errors'])) {
            $this->error('Fehler:');
            foreach ($stats['errors'] as $error) {
                $this->line('  - ' . $error);
            }
        }

        $this->info('JobRad-Import abgeschlossen.');

        return self::SUCCESS;
    }
}


