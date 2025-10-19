<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\BhgImportService;

class ImportBhgData extends Command
{
    protected $signature = 'hcm:import-bhg {csv_path} {--team-id=} {--user-id=} {--employer-id=}';
    protected $description = 'Import BHG employee data from CSV file';

    public function handle()
    {
        $csvPath = $this->argument('csv_path');
        $teamId = $this->option('team-id') ?: auth()->user()->currentTeam->id;
        $userId = $this->option('user-id') ?: auth()->id();
        $employerId = $this->option('employer-id');

        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        $this->info("Starting BHG import from: {$csvPath}");
        $this->info("Team ID: {$teamId}, User ID: {$userId}, Employer ID: {$employerId}");

        $importService = new BhgImportService($teamId, $userId, $employerId);
        
        // Wenn employerId gesetzt ist, zeige die tatsächliche team_id
        if ($employerId) {
            $employer = \Platform\Hcm\Models\HcmEmployer::find($employerId);
            if ($employer) {
                $this->info("Using team_id from employer: {$employer->team_id}");
            }
        }
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
