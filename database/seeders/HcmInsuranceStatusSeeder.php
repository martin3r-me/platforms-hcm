<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmInsuranceStatus;

class HcmInsuranceStatusSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? $this->command->option('team-id') : (config('hcm.seeder_team_id') ?? (auth()->user()->current_team_id ?? 0));

        $items = [
            ['code' => '109', 'name' => 'Pflichtversichert (gesetzlich)'],
            ['code' => '110', 'name' => 'Freiwillig gesetzlich versichert'],
            ['code' => 'PRIV', 'name' => 'Privat krankenversichert'],
            ['code' => 'STUD', 'name' => 'Studentische Versicherung'],
            ['code' => 'FAM', 'name' => 'Familienversichert'],
            ['code' => 'KVDR', 'name' => 'Krankenversicherung der Rentner'],
            ['code' => 'BEAM', 'name' => 'Beihilfe/Beamte (ergänzend)'],
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


