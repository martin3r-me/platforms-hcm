<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Database\Seeders\HcmHealthInsuranceCompanySeeder;

class ImportHealthInsuranceCompanies extends Command
{
    protected $signature = 'hcm:import-health-insurance-companies {--team-id=}';
    protected $description = 'Import all German health insurance companies with IK numbers';

    public function handle()
    {
        $teamId = $this->option('team-id') ?: auth()->user()->current_team_id;
        
        if (!$teamId) {
            $this->error('No team ID provided. Use --team-id option or ensure user has current team.');
            return 1;
        }

        $this->info("Importing health insurance companies for team ID: {$teamId}");
        
        // Run the seeder
        $seeder = new HcmHealthInsuranceCompanySeeder();
        $seeder->setCommand($this);
        $seeder->run();
        
        $this->info('Health insurance companies imported successfully!');
        return 0;
    }
}
