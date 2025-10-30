<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;

class HcmLookupSeeder extends Seeder
{
    public function run(): void
    {
        // Optional: team-id kann per --team-id Option ans jeweilige Seeder-Command durchgereicht werden,
        // hier rufen wir einfach die Seeder-Klassen direkt auf.
        (new HcmInsuranceStatusSeeder())->run();
        (new HcmPensionTypeSeeder())->run();
        (new HcmEmploymentRelationshipSeeder())->run();
        (new HcmLevyTypeSeeder())->run();
        (new HcmPersonGroupSeeder())->run();
    }
}


