<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmEmployeeContract;
use Carbon\Carbon;

class DeactivateExpiredContracts extends Command
{
    protected $signature = 'hcm:deactivate-expired-contracts 
                            {--dry-run : Show what would be deactivated without actually doing it}
                            {--date= : Use a specific date instead of today (format: Y-m-d)}';

    protected $description = 'Deactivate contracts that have passed their end date';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date')) 
            : Carbon::today();

        $this->info("Checking for expired contracts as of {$date->format('d.m.Y')}...");

        // Find all active contracts with an end_date in the past
        $expiredContracts = HcmEmployeeContract::where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $date->toDateString())
            ->with(['employee.crmContactLinks.contact'])
            ->get();

        if ($expiredContracts->isEmpty()) {
            $this->info('No expired contracts found.');
            return 0;
        }

        $this->info("Found {$expiredContracts->count()} expired contract(s):");
        $this->newLine();

        $tableData = [];
        foreach ($expiredContracts as $contract) {
            $employeeName = $contract->employee->getContact()?->full_name 
                ?? $contract->employee->employee_number;
            
            $tableData[] = [
                'ID' => $contract->id,
                'Employee' => $employeeName,
                'Start Date' => $contract->start_date?->format('d.m.Y') ?? '-',
                'End Date' => $contract->end_date->format('d.m.Y'),
                'Days Expired' => $contract->end_date->diffInDays($date),
            ];
        }

        $this->table(
            ['ID', 'Employee', 'Start Date', 'End Date', 'Days Expired'],
            $tableData
        );

        if ($dryRun) {
            $this->warn('DRY RUN: No contracts were deactivated.');
            $this->info("Run without --dry-run to deactivate these contracts.");
            return 0;
        }

        if (!$this->confirm('Do you want to deactivate these contracts?', true)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $deactivated = 0;
        foreach ($expiredContracts as $contract) {
            $contract->update(['is_active' => false]);
            $deactivated++;
        }

        $this->info("Successfully deactivated {$deactivated} contract(s).");
        return 0;
    }
}

