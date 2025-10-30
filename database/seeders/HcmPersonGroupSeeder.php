<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmPersonGroup;

class HcmPersonGroupSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? (int) $this->command->option('team-id') : (int) (config('hcm.seeder_team_id') ?? (auth()->user()->current_team_id ?? 0));

        // Erweiterte Standardliste (Auszug, BA/DEÜV-Orientierung)
        $items = [
            ['code' => '101', 'name' => 'Arbeitnehmer (sozialversicherungspflichtig)'],
            ['code' => '102', 'name' => 'Auszubildende'],
            ['code' => '103', 'name' => 'Umschüler/Weiterbildungsteilnehmer'],
            ['code' => '104', 'name' => 'Werkstudenten'],
            ['code' => '105', 'name' => 'Studentische Aushilfen'],
            ['code' => '106', 'name' => 'Aushilfskräfte (kurzfristig)'],
            ['code' => '107', 'name' => 'Aushilfskräfte (geringfügig)'],
            ['code' => '108', 'name' => 'Teilnehmer an dualem Studium'],
            ['code' => '109', 'name' => 'Praktikanten/Volontäre'],
            ['code' => '110', 'name' => 'Rentner (beschäftigt)'],
            ['code' => '111', 'name' => 'Werk-/Dienstvertragsnehmer (sozialversicherungspflichtig)'],
            ['code' => '112', 'name' => 'Elternzeit (beschäftigt)'],
            ['code' => '113', 'name' => 'Sabbatical/ohne Entgelt (beschäftigt)'],
            ['code' => '120', 'name' => 'Leiharbeitnehmer (überlassen)'],
            ['code' => '121', 'name' => 'Stammpersonal Verleiher'],
        ];

        foreach ($items as $i) {
            $exists = HcmPersonGroup::where('team_id', $teamId)->where('code', $i['code'])->exists();
            if (!$exists) {
                HcmPersonGroup::create([
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


