<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmPensionType;

class HcmPensionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? $this->command->option('team-id') : (config('hcm.seeder_team_id') ?? (auth()->user()->current_team_id ?? 0));

        $items = [
            ['code' => 'ALT', 'name' => 'Altersrente'],
            ['code' => 'FRU', 'name' => 'Altersrente für langjährig Versicherte (früh)'],
            ['code' => 'LANG', 'name' => 'Altersrente für besonders langjährig Versicherte'],
            ['code' => 'TEIL', 'name' => 'Teilrente'],
            ['code' => 'ERW', 'name' => 'Erwerbsminderungsrente'],
            ['code' => 'WIT', 'name' => 'Witwen-/Witwerrente'],
            ['code' => 'WAIS', 'name' => 'Waisenrente'],
            ['code' => 'BGR', 'name' => 'Betriebsrente'],
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


