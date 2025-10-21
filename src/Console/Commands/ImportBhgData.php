<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\BhgImportService;

class ImportBhgData extends Command
{
    protected $signature = 'hcm:import-bhg {csv_path} {--employer-id=} {--dry-run : Show what would be imported without actually importing}';
    protected $description = 'Import BHG employee data from CSV file';

    public function handle()
    {
        $csvPath = $this->argument('csv_path');
        $employerId = $this->option('employer-id');
        $dryRun = $this->option('dry-run');

        // Validierung der erforderlichen Parameter
        if (!$employerId) {
            $this->error('Arbeitgeber-ID ist erforderlich. Verwende --employer-id=1');
            return 1;
        }

        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        // Arbeitgeber laden und Team-ID/User-ID ermitteln
        $employer = \Platform\Hcm\Models\HcmEmployer::find($employerId);
        if (!$employer) {
            $this->error("Arbeitgeber mit ID {$employerId} nicht gefunden");
            return 1;
        }

        if ($dryRun) {
            $this->info("DRY RUN MODE - No data will be written");
        }

        $this->info("Starting BHG import from: {$csvPath}");
        $this->info("Employer: {$employer->display_name} (Team: {$employer->team_id})");

        $importService = new BhgImportService($employer->team_id, $employer->created_by_user_id, $employerId);
        
        if ($dryRun) {
            $stats = $importService->dryRunFromCsv($csvPath);
        } else {
            $stats = $importService->importFromCsv($csvPath);
        }

        if ($dryRun) {
            $this->info("DRY RUN completed - No data was written!");
        } else {
            $this->info("Import completed!");
        }
        
        $this->info("Cost centers that would be created: {$stats['cost_centers_created']}");
        $this->info("Employees that would be created: {$stats['employees_created']}");
        $this->info("Employees that would be updated: {$stats['employees_updated']}");
        $this->info("Job titles that would be created: {$stats['job_titles_created']}");
        $this->info("Job activities that would be created: {$stats['job_activities_created']}");
        $this->info("Contracts that would be created: {$stats['contracts_created']}");
        $this->info("Contracts that would be updated: {$stats['contracts_updated']}");
        $this->info("Contract activity links that would be created: {$stats['contract_activity_links_created']}");
        $this->info("CRM contacts that would be created: {$stats['crm_contacts_created']}");
        $this->info("CRM company relations that would be created: {$stats['crm_company_relations_created']}");

        if (!empty($stats['errors'])) {
            $this->error("Errors encountered:");
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }

        return 0;
    }
}
