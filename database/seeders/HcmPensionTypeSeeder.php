<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmPensionType;

class HcmPensionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? $this->command->option('team-id') : auth()->user()->current_team_id;

        $items = [
            ['code' => 'ALT', 'name' => 'Altersrente'],
            ['code' => 'ERW', 'name' => 'Erwerbsminderungsrente'],
            ['code' => 'WIT', 'name' => 'Witwen-/Witwerrente'],
        ];

        foreach ($items as $i) {
            $exists = HcmPensionType::where('team_id', $teamId)->where('code', $i['code'])->exists();
            if (!$exists) {
                HcmPensionType::create([
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


