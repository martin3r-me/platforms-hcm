<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmEmploymentRelationship;

class HcmEmploymentRelationshipSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? $this->command->option('team-id') : auth()->user()->current_team_id;

        $items = [
            ['code' => 'FT', 'name' => 'Vollzeit'],
            ['code' => 'PT', 'name' => 'Teilzeit'],
            ['code' => 'MINI', 'name' => 'Minijob'],
            ['code' => 'TEMP', 'name' => 'Befristet'],
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


