<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmEmploymentRelationship;

class HcmEmploymentRelationshipSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? $this->command->option('team-id') : (config('hcm.seeder_team_id') ?? (auth()->user()->current_team_id ?? 0));

        $items = [
            ['code' => 'FT', 'name' => 'Vollzeit (unbefristet)'],
            ['code' => 'PT', 'name' => 'Teilzeit (unbefristet)'],
            ['code' => 'FTB', 'name' => 'Vollzeit (befristet)'],
            ['code' => 'PTB', 'name' => 'Teilzeit (befristet)'],
            ['code' => 'MINI', 'name' => 'Geringfügig entlohnt (Minijob)'],
            ['code' => 'KURZ', 'name' => 'Kurzfristige Beschäftigung'],
            ['code' => 'AUSB', 'name' => 'Ausbildung (dual)'],
            ['code' => 'PRAK', 'name' => 'Praktikum'],
            ['code' => 'WERK', 'name' => 'Werkstudent'],
            ['code' => 'LEIHE', 'name' => 'Leiharbeitnehmer'],
            ['code' => 'HOME', 'name' => 'Homeoffice/Remote (vertraglich)'],
        ];

        foreach ($items as $i) {
            $exists = HcmEmploymentRelationship::where('team_id', $teamId)->where('code', $i['code'])->exists();
            if (!$exists) {
                HcmEmploymentRelationship::create([
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


