<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\BhgImportService;

class ImportBhgData extends Command
{
    protected $signature = 'hcm:import-bhg {csv_path} {--employer-id=}';
    protected $description = 'Import BHG employee data from CSV file';

    public function handle()
    {
        $csvPath = $this->argument('csv_path');
        $employerId = $this->option('employer-id');

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

        $this->info("Starting BHG import from: {$csvPath}");
        $this->info("Employer: {$employer->display_name} (Team: {$employer->team_id})");

        $importService = new BhgImportService($employer->team_id, $employer->created_by_user_id, $employerId);
        $stats = $importService->importFromCsv($csvPath);

        $this->info("Import completed!");
        $this->info("Employees created: {$stats['employees_created']}");
        $this->info("Job titles created: {$stats['job_titles_created']}");
        $this->info("Job activities created: {$stats['job_activities_created']}");
        $this->info("CRM contacts created: {$stats['crm_contacts_created']}");
        $this->info("CRM company relations created: {$stats['crm_company_relations_created']}");

        if (!empty($stats['errors'])) {
            $this->error("Errors encountered:");
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }

        return 0;
    }
}
