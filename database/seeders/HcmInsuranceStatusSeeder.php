<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmInsuranceStatus;

class HcmInsuranceStatusSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? $this->command->option('team-id') : auth()->user()->current_team_id;

        $items = [
            ['code' => '109', 'name' => 'Pflichtversichert (PGR 109)'],
            ['code' => '110', 'name' => 'Freiwillig versichert (PGR 110)'],
            ['code' => 'PRIV', 'name' => 'Privat versichert'],
        ];

        foreach ($items as $i) {
            $exists = HcmInsuranceStatus::where('team_id', $teamId)->where('code', $i['code'])->exists();
            if (!$exists) {
                HcmInsuranceStatus::create([
                    'code' => $i['code'],
                    'name' => $i['name'],
                    'is_active' => true,
                    'team_id' => $teamId,
                    'created_by_user_id' => auth()->id(),
                ]);
            }
        }
    }
}


