<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmLevyType;

class HcmLevyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? $this->command->option('team-id') : auth()->user()->current_team_id;

        $items = [
            ['code' => 'U1', 'name' => 'Umlage U1'],
            ['code' => 'U2', 'name' => 'Umlage U2'],
            ['code' => 'INSO', 'name' => 'Insolvenzgeldumlage'],
        ];

        foreach ($items as $i) {
            $exists = HcmLevyType::where('team_id', $teamId)->where('code', $i['code'])->exists();
            if (!$exists) {
                HcmLevyType::create([
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


