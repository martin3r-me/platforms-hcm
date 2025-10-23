<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\EmployeeTariffAssignmentService;

class AssignEmployeeTariffs extends Command
{
    protected $signature = 'hcm:assign-employee-tariffs 
                            {--file= : CSV file with employee assignments}
                            {--show-unassigned : Show employees without tariff assignment}
                            {--show-assigned : Show employees with tariff assignment}';

    protected $description = 'Assign employees to tariff groups and levels';

    public function handle(EmployeeTariffAssignmentService $assignmentService): int
    {
        if ($this->option('show-unassigned')) {
            $this->showUnassignedEmployees($assignmentService);
            return 0;
        }

        if ($this->option('show-assigned')) {
            $this->showAssignedEmployees($assignmentService);
            return 0;
        }

        $filePath = $this->option('file');
        if (!$filePath) {
            $this->error('File path is required. Use --file=path/to/file.csv');
            return 1;
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Importing employee tariff assignments from: {$filePath}");

        $results = $assignmentService->importAssignmentsFromCsv($filePath);

        $this->info("Import completed:");
        $this->line("- Records processed: {$results['processed']}");
        $this->line("- Assignments created: {$results['assigned']}");

        if (!empty($results['errors'])) {
            $this->warn("Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->line("- " . $error['error']);
            }
        }

        return 0;
    }

    private function showUnassignedEmployees(EmployeeTariffAssignmentService $assignmentService): void
    {
        $employees = $assignmentService->getUnassignedEmployees();

        $this->info("Employees without tariff assignment ({$employees->count()}):");
        
        $headers = ['ID', 'Employee Number', 'Name', 'Start Date'];
        $rows = [];

        foreach ($employees as $contract) {
            $rows[] = [
                $contract->id,
                $contract->employee->employee_number ?? 'N/A',
                $contract->employee->crmContactLinks->first()?->contact->name ?? 'N/A',
                $contract->start_date->format('Y-m-d'),
            ];
        }

        $this->table($headers, $rows);
    }

    private function showAssignedEmployees(EmployeeTariffAssignmentService $assignmentService): void
    {
        $employees = $assignmentService->getAssignedEmployees();

        $this->info("Employees with tariff assignment ({$employees->count()}):");
        
        $headers = ['ID', 'Employee Number', 'Name', 'Tariff Group', 'Tariff Level', 'Next Progression'];
        $rows = [];

        foreach ($employees as $contract) {
            $rows[] = [
                $contract->id,
                $contract->employee->employee_number ?? 'N/A',
                $contract->employee->crmContactLinks->first()?->contact->name ?? 'N/A',
                $contract->tariffGroup->code ?? 'N/A',
                $contract->tariffLevel->code ?? 'N/A',
                $contract->next_tariff_level_date ?? 'Final level',
            ];
        }

        $this->table($headers, $rows);
    }
}
