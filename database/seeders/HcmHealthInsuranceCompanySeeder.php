<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmHealthInsuranceCompany;

class HcmHealthInsuranceCompanySeeder extends Seeder
{
    public function run(): void
    {
        $teamId = $this->command ? ($this->command->option('team-id') ?: config('hcm.seeder_team_id')) : (config('hcm.seeder_team_id') ?: auth()->user()->current_team_id);
        
        if ($this->command) {
            $this->command->info("Importing health insurance companies for team ID: {$teamId}");
        }
        $healthInsuranceCompanies = [
            ['name' => 'AOK - Die Gesundheitskasse für Niedersachsen', 'code' => '29720865'],
            ['name' => 'AOK Baden-Württemberg Hauptverwaltung', 'code' => '67450665'],
            ['name' => 'AOK Bayern Die Gesundheitskasse', 'code' => '87880235'],
            ['name' => 'AOK Bremen/Bremerhaven', 'code' => '20012084'],
            ['name' => 'AOK Hessen Direktion', 'code' => '45118687'],
            ['name' => 'AOK Nordost - Die Gesundheitskasse', 'code' => '90235319'],
            ['name' => 'AOK NordWest', 'code' => '33526082'],
            ['name' => 'AOK PLUS Die Gesundheitskasse', 'code' => '5174740'],
            ['name' => 'AOK Rheinland/Hamburg Die Gesundheitskasse', 'code' => '34364249'],
            ['name' => 'AOK Rheinland-Pfalz/Saarland', 'code' => '51605725'],
            ['name' => 'AOK Sachsen-Anhalt', 'code' => '1029141'],
            ['name' => 'Audi BKK Rechtskreis West und Ost', 'code' => '82889062'],
            ['name' => 'Augenoptiker Ausgleichskasse VVaG', 'code' => '33868451'],
            ['name' => 'BAHN-BKK', 'code' => '49003443'],
            ['name' => 'BARMER (vormals BARMER GEK)', 'code' => '42938966'],
            ['name' => 'BERGISCHE KRANKENKASSE', 'code' => '42039708'],
            ['name' => 'Bertelsmann BKK Rechtskreis West und Ost', 'code' => '31323584'],
            ['name' => 'Betriebskrankenkasse der Energieversorgung Mittelr', 'code' => '51980490'],
            ['name' => 'Betriebskrankenkasse Groz-Beckert', 'code' => '60393261'],
            ['name' => 'Betriebskrankenkasse VerbundPlus', 'code' => '69785429'],
            ['name' => 'BIG direkt gesund', 'code' => '97141402'],
            ['name' => 'BKK Akzo Nobel Bayern', 'code' => '71579930'],
            ['name' => 'BKK B. Braun Aesculap', 'code' => '47034975'],
            ['name' => 'BKK BPW Wiehl', 'code' => '30980327'],
            ['name' => 'BKK Deutsche Bank AG', 'code' => '34401277'],
            ['name' => 'BKK Diakonie', 'code' => '31323686'],
            ['name' => 'BKK DürkoppAdler', 'code' => '31323799'],
            ['name' => 'BKK EUREGIO', 'code' => '30168049'],
            ['name' => 'BKK EWE', 'code' => '26515319'],
            ['name' => 'BKK exklusiv', 'code' => '22178373'],
            ['name' => 'BKK Faber-Castell & Partner', 'code' => '86772584'],
            ['name' => 'BKK firmus', 'code' => '20156168'],
            ['name' => 'BKK FREUDENBERG', 'code' => '63922962'],
            ['name' => 'BKK GILDEMEISTER SEIDENSTICKER', 'code' => '31323802'],
            ['name' => 'BKK Grillo-Werke', 'code' => '35087667'],
            ['name' => 'BKK Herford Minden Ravensberg', 'code' => '36916980'],
            ['name' => 'BKK Herkules vorher BKK Wegmann bis 31.12.2000', 'code' => '47034953'],
            ['name' => 'BKK Linde', 'code' => '48698889'],
            ['name' => 'BKK MAHLE', 'code' => '67572537'],
            ['name' => 'bkk melitta hmr', 'code' => '36916935'],
            ['name' => 'BKK Miele', 'code' => '31323700'],
            ['name' => 'BKK Mobil Oil', 'code' => '15517302'],
            ['name' => 'BKK MTU', 'code' => '65710574'],
            ['name' => 'BKK PFAFF', 'code' => '51588416'],
            ['name' => 'BKK Pfalz', 'code' => '52598579'],
            ['name' => 'BKK ProVita', 'code' => '88571250'],
            ['name' => 'BKK Public', 'code' => '21488086'],
            ['name' => 'BKK PwC', 'code' => '47307817'],
            ['name' => 'BKK Rieker.RICOSTA.Weisser', 'code' => '66626976'],
            ['name' => 'BKK RWE', 'code' => '16665321'],
            ['name' => 'BKK Salzgitter', 'code' => '21203214'],
            ['name' => 'BKK Scheufelen', 'code' => '61232758'],
            ['name' => 'BKK Schwarzwald-Baar-Heuberg', 'code' => '66614249'],
            ['name' => 'BKK Stadt Augsburg', 'code' => '81211334'],
            ['name' => 'BKK Technoform', 'code' => '23446040'],
            ['name' => 'BKK Textilgruppe Hof', 'code' => '73170269'],
            ['name' => 'BKK VDN', 'code' => '37416328'],
            ['name' => 'BKK Verkehrsbau Union', 'code' => '92644250'],
            ['name' => 'BKK Voralb HELLERINDEXLEUZE', 'code' => '97352653'],
            ['name' => 'BKK Werra-Meissner', 'code' => '44037562'],
            ['name' => 'BKK Wirtschaft & Finanzen', 'code' => '46967693'],
            ['name' => 'BKK Würth', 'code' => '67161380'],
            ['name' => 'BKK ZF & Partner', 'code' => '69753266'],
            ['name' => 'BKK24', 'code' => '23709856'],
            ['name' => 'BMW BKK Zentrale', 'code' => '87271125'],
            ['name' => 'BOSCH BKK', 'code' => '67572593'],
            ['name' => 'Continentale Betriebskrankenkasse', 'code' => '33865367'],
            ['name' => 'Daimler Betriebskrankenkasse', 'code' => '68216980'],
            ['name' => 'DAK-Gesundheit', 'code' => '48698890'],
            ['name' => 'Debeka BKK', 'code' => '52156763'],
            ['name' => 'energie-BKK Hauptverwaltung', 'code' => '29717581'],
            ['name' => 'Ernst & Young BKK', 'code' => '46939789'],
            ['name' => 'Heimat Krankenkasse', 'code' => '31209131'],
            ['name' => 'HEK Hanseatische Krankenkasse', 'code' => '15031806'],
            ['name' => 'hkk Handelskrankenkasse', 'code' => '20013461'],
            ['name' => 'IKK - Die Innovationskasse', 'code' => '14228571'],
            ['name' => 'IKK Brandenburg und Berlin', 'code' => '1020803'],
            ['name' => 'IKK classic', 'code' => '1049203'],
            ['name' => 'IKK gesund plus (Ost) Hauptverwaltung', 'code' => '1000455'],
            ['name' => 'IKK Südwest', 'code' => '55811201'],
            ['name' => 'KARL MAYER Betriebskrankenkasse', 'code' => '48063096'],
            ['name' => 'KKH Kaufmännische Krankenkasse', 'code' => '29137937'],
            ['name' => 'Knappschaft (Bahn-See-Hauptverwaltung)', 'code' => '98000006'],
            ['name' => 'Koenig & Bauer BKK', 'code' => '75925585'],
            ['name' => 'Krones BKK', 'code' => '74157435'],
            ['name' => 'Merck BKK', 'code' => '44377882'],
            ['name' => 'mhplus Betriebskrankenkasse West', 'code' => '63494759'],
            ['name' => 'NOVITAS Betriebskrankenkasse', 'code' => '35134022'],
            ['name' => 'pronova BKK', 'code' => '15872672'],
            ['name' => 'R+V Betriebskrankenkasse', 'code' => '48944809'],
            ['name' => 'Salus BKK', 'code' => '44953697'],
            ['name' => 'SBK HV West', 'code' => '87954699'],
            ['name' => 'SECURVITA BKK', 'code' => '15517482'],
            ['name' => 'SIEMAG BKK', 'code' => '41378558'],
            ['name' => 'SKD BKK', 'code' => '74773896'],
            ['name' => 'Südzucker BKK', 'code' => '62332660'],
            ['name' => 'SVLFG, Landwirtschaftliche Krankenkasse', 'code' => '39873587'],
            ['name' => 'Techniker Krankenkasse -Rechtskreis West und Ost-', 'code' => '15027365'],
            ['name' => 'TUI BKK', 'code' => '29074470'],
            ['name' => 'VIACTIV Krankenkasse', 'code' => '40180080'],
            ['name' => 'vivida bkk', 'code' => '66458477'],
            ['name' => 'Wieland BKK', 'code' => '68659646'],
            ['name' => 'WMF Betriebskrankenkasse', 'code' => '61232769'],
        ];

        $imported = 0;
        $skipped = 0;
        
        foreach ($healthInsuranceCompanies as $company) {
            $exists = HcmHealthInsuranceCompany::where('code', $company['code'])
                ->where('team_id', $teamId)
                ->exists();
                
            if (!$exists) {
                HcmHealthInsuranceCompany::create([
                    'name' => $company['name'],
                    'code' => $company['code'],
                    'ik_number' => $company['code'], // Code ist die IK-Nummer
                    'is_active' => true,
                    'team_id' => $teamId,
                    'created_by_user_id' => auth()->id(),
                ]);
                $imported++;
            } else {
                $skipped++;
            }
        }
        
        if ($this->command) {
            $this->command->info("Import completed: {$imported} new companies imported, {$skipped} already existed.");
        }
    }
}
