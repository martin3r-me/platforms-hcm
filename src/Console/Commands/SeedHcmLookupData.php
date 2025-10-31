<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Database\Seeders\HcmInsuranceStatusSeeder;
use Platform\Hcm\Database\Seeders\HcmPensionTypeSeeder;
use Platform\Hcm\Database\Seeders\HcmEmploymentRelationshipSeeder;
use Platform\Hcm\Database\Seeders\HcmLevyTypeSeeder;
use Platform\Hcm\Database\Seeders\HcmPersonGroupSeeder;
// duplicate import removed

class SeedHcmLookupData extends Command
{
    protected $signature = 'hcm:seed-lookup-data 
                            {--team-id= : Team-ID für die Seeding-Datensätze}
                            {--force : Force in Production}';

    protected $description = 'Seedet HCM Lookup-Daten (Versicherungsstatus, Rentenarten, Beschäftigungsverhältnisse, Umlagearten, Personengruppen)';

    public function handle(): int
    {
        if (!$this->option('force') && app()->environment('production')) {
            $this->warn('Running in production. Use --force to proceed.');
            return 1;
        }

        $teamId = (int) ($this->option('team-id') ?? 0);
        if ($teamId <= 0) {
            $this->error('Bitte --team-id angeben.');
            return 1;
        }

        // Team-ID per Config durchreichen, damit Seeder sie ohne $this->command nutzen können
        config(['hcm.seeder_team_id' => $teamId]);

        $this->info('Seeding HCM lookup data for team: ' . $teamId);

        (new HcmInsuranceStatusSeeder())->run();
        (new HcmPensionTypeSeeder())->run();
        (new HcmEmploymentRelationshipSeeder())->run();
        (new HcmLevyTypeSeeder())->run();
        (new HcmPersonGroupSeeder())->run();

        $this->info('✅ HCM lookup data seeded successfully.');
        return 0;
    }
}


