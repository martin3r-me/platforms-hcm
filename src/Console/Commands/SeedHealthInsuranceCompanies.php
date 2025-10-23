<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Database\Seeders\HcmHealthInsuranceCompanySeeder;

class SeedHealthInsuranceCompanies extends Command
{
    protected $signature = 'hcm:seed-health-insurance-companies {--team-id= : Team ID for the health insurance companies}';
    protected $description = 'Seeds health insurance companies with German data';

    public function handle()
    {
        $teamId = $this->option('team-id');
        
        if (!$teamId) {
            $teamId = $this->ask('Enter Team ID', 1);
        }
        
        $this->info("Seeding health insurance companies for team {$teamId}...");
        
        $seeder = new HcmHealthInsuranceCompanySeeder();
        $seeder->setCommand($this);
        $seeder->run();
        
        $this->info('Health insurance companies seeded successfully!');
    }
}
