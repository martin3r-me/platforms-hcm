<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\PayrollTypeImportService;

class ImportPayrollTypes extends Command
{
    protected $signature = 'hcm:import-payroll-types {csv_path} {--team-id=} {--user-id=} {--dry-run}';
    protected $description = 'Import payroll types from CSV file';

    public function handle()
    {
        $csvPath = $this->argument('csv_path');
        $teamId = $this->option('team-id');
        $userId = $this->option('user-id');
        $dryRun = $this->option('dry-run');

        if (!$teamId || !$userId) {
            $this->error('Please provide --team-id and --user-id options');
            return 1;
        }

        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        $this->info("Starting payroll types import from: {$csvPath}");
        $this->info("Team ID: {$teamId}, User ID: {$userId}");
        
        if ($dryRun) {
            $this->info("Running in DRY RUN mode - no data will be written");
        }

        $service = new PayrollTypeImportService($teamId, $userId);

        if ($dryRun) {
            $stats = $service->dryRunFromCsv($csvPath);
            $this->info("Dry run completed!");
        } else {
            $stats = $service->importFromCsv($csvPath);
            $this->info("Import completed!");
        }

        // Display statistics
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total CSV Rows', $stats['total_rows'] ?? 0],
                ['Unique LANR Numbers', $stats['unique_lanr_count'] ?? 0],
                ['Unique Combinations (LANR+Konten)', $stats['unique_combinations'] ?? 0],
                ['Duplicate Rows Skipped', $stats['duplicate_rows'] ?? 0],
                ['Payroll Types Created', $stats['payroll_types_created']],
                ['Payroll Types Updated', $stats['payroll_types_updated']],
                ['Errors', count($stats['errors'])],
            ]
        );

        // Show duplicate analysis if there are duplicates
        if (!empty($stats['duplicates'])) {
            $this->warn("Found duplicate LANR numbers:");
            $duplicateCounts = array_count_values($stats['duplicates']);
            foreach ($duplicateCounts as $lanr => $count) {
                $this->line("- LANR {$lanr}: {$count} duplicates");
            }
        }

        if (!empty($stats['errors'])) {
            $this->error("Errors encountered:");
            foreach ($stats['errors'] as $error) {
                $this->error("- {$error}");
            }
        }

        return 0;
    }
}
