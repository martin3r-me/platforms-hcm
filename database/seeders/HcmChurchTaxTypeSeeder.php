<?php

namespace Platform\Hcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Hcm\Models\HcmChurchTaxType;

class HcmChurchTaxTypeSeeder extends Seeder
{
    public function run(): void
    {
        $churchTaxTypes = [
            ['code' => 'AK', 'name' => 'Altkatholische Kirchensteuer', 'description' => 'Altkatholische Kirchensteuer'],
            ['code' => 'EV', 'name' => 'Evangelische Kirchensteuer', 'description' => 'Evangelische Kirchensteuer'],
            ['code' => 'FA', 'name' => 'Freie Religionsgemeinschaft Alzey', 'description' => 'Freie Religionsgemeinschaft Alzey'],
            ['code' => 'FB', 'name' => 'Kirchensteuer der Freireligiösen Landesgemeinde Baden', 'description' => 'Kirchensteuer der Freireligiösen Landesgemeinde Baden'],
            ['code' => 'FG', 'name' => 'Freireligiöse Landesgemeinde Pfalz', 'description' => 'Freireligiöse Landesgemeinde Pfalz'],
            ['code' => 'FM', 'name' => 'Freireligiöse Gemeinde Mainz', 'description' => 'Freireligiöse Gemeinde Mainz'],
            ['code' => 'FR', 'name' => 'Französisch reformiert', 'description' => 'Französisch reformiert'],
            ['code' => 'FS', 'name' => 'Freireligiöse Gemeinde Offenbach/Mainz', 'description' => 'Freireligiöse Gemeinde Offenbach/Mainz'],
            ['code' => 'IB', 'name' => 'Kirchensteuer der Israelitischen Religionsgemeinschaft Baden', 'description' => 'Kirchensteuer der Israelitischen Religionsgemeinschaft Baden'],
            ['code' => 'IH', 'name' => 'Jüdische Kultussteuer (Schleswig Holstein)', 'description' => 'Jüdische Kultussteuer (Schleswig Holstein)'],
            ['code' => 'IL', 'name' => 'Israelitische Kultussteuer der kultusberechtigten Gemeinden (Hessen)', 'description' => 'Israelitische Kultussteuer der kultusberechtigten Gemeinden (Hessen)'],
            ['code' => 'IS', 'name' => 'Israelitische Kultussteuer Frankfurt/Israelitische Bekenntnissteuer (Bayern)', 'description' => 'Israelitische Kultussteuer Frankfurt/Israelitische Bekenntnissteuer (Bayern)'],
            ['code' => 'IW', 'name' => 'Kirchensteuer der Israelitischen Religionsgemeinschaft Württembergs', 'description' => 'Kirchensteuer der Israelitischen Religionsgemeinschaft Württembergs'],
            ['code' => 'JD', 'name' => 'Jüdische Kultussteuer', 'description' => 'Jüdische Kultussteuer'],
            ['code' => 'JH', 'name' => 'Jüdische Kultussteuer (Hamburg)', 'description' => 'Jüdische Kultussteuer (Hamburg)'],
            ['code' => 'LT', 'name' => 'Evangelisch lutherisch', 'description' => 'Evangelisch lutherisch'],
            ['code' => 'NA', 'name' => 'Neuapostolisch', 'description' => 'Neuapostolisch'],
            ['code' => 'RF', 'name' => 'Evangelisch reformiert', 'description' => 'Evangelisch reformiert'],
            ['code' => 'RK', 'name' => 'Römisch-Katholische Kirchensteuer', 'description' => 'Römisch-Katholische Kirchensteuer'],
        ];

        foreach ($churchTaxTypes as $type) {
            HcmChurchTaxType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}

