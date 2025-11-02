<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmExportTemplate;
use Platform\Hcm\Models\HcmExportTemplateColumn;

class CreateInfoniqaExportTemplate extends Command
{
    protected $signature = 'hcm:create-infoniqa-template {--team-id= : Team ID (default: 1)}';
    protected $description = 'Erstellt das INFONIQA-Export-Template mit allen Spalten-Mappings';

    public function handle(): int
    {
        $teamId = (int) ($this->option('team-id') ?: 1);

        // Prüfe ob Template bereits existiert
        $existing = HcmExportTemplate::where('team_id', $teamId)
            ->where('slug', 'infoniqa-employee-export')
            ->first();

        if ($existing) {
            $this->warn("Template 'infoniqa-employee-export' existiert bereits für Team {$teamId}");
            if (!$this->confirm('Soll es gelöscht und neu erstellt werden?')) {
                return 0;
            }
            $existing->columns()->delete();
            $existing->delete();
        }

        $this->info("Erstelle INFONIQA-Export-Template für Team {$teamId}...");

        // Template erstellen
        $template = HcmExportTemplate::create([
            'team_id' => $teamId,
            'created_by_user_id' => null, // System-Template
            'name' => 'INFONIQA Mitarbeiter-Export',
            'slug' => 'infoniqa-employee-export',
            'description' => 'Export-Template für INFONIQA-Lohnabrechnung mit 199 Spalten und 5 Header-Zeilen',
            'configuration' => [
                'header_rows' => 5,
                'total_columns' => 199,
                'delimiter' => ';',
                'bom' => true,
                'requires_employer_id' => true,
            ],
            'is_active' => true,
            'is_system_template' => true,
        ]);

        $this->info("Template erstellt. Füge Spalten hinzu...");

        // Header-Zeile 5 (die eigentlichen Spaltennamen)
        $headers = explode(';', 'Nr.;Anrede;Titel;Namenszusatz;Vorname;Namensvorsatz;Name;Straße;Hausnr.;Hausnr.-zusatz;Adresszusatz;Länderkennzeichen;PLZ Code;Ort;Betriebsteilcode;Abrechnungskreis;Eintrittsdatum;Austrittsdatum;Austrittsgrund;Vertragsende;Befristungsgrund;Ende Probezeit;Entlassung / Kündigung am;unwiderrufliche Freistellung am;Betriebszugehörigkeit seit;Berufserf./Ausbildung seit;Ende der Ausbildung;Personengruppenschlüssel;Beitragsgruppe;BYGR KV;KV-Kennzeichen;KV Vertragsart;KK-Code (Einzugsstelle);KV-Beitrag privat;BYGR RV;RV-Kennzeichen;BYGR AV;AV-Kennzeichen;BYGR PV;PV-Kennzeichen;Kinder;Kinder unter 25 Jahren für PV-Abschlag;PV-Beitrag privat;Umlagepflicht U1 Lfz;Umlagepflicht U2;Umlagepflicht Insolvenz;Staatsangehörigkeitsschlüssel;;Rentenbeginn;Befreiung von RV-Pflicht ab (§ 6 Abs.1b SGBVI);Beschäftigungsverhältnis;Mehrfachbeschäftigt;Rentenart;Altersrente beantragt am;Saisonarbeitnehmer;Soz.-Versicherungsnr.;Geschlecht;Geburtsdatum;Geburtsname;Geburtsort;Namenszusatz Geburtsname;Namensvorsatz Geburtsname;Geburtsland;Krankenkasse (tats.);PGR 109/110:Versicherungsstatus;steuerpflichtig EStG § 1;Art der Besteuerung;wer trägt die Steuer;Identifikationsnummer;abw. Geburtsdatum lt. Pass;Haupt AG (Steuer);Herkunft LSt.-Merkmale gem. EStG;Steuerklasse;Faktor nach § 39f EStG;Kinderfreibetrag;Freibetrag Monat;Freibetrag Jahr;Hinzurechnung Monat;Hinzurechnung Jahr;Konfession;Konfession Ehepartner;Lohnsteuerspezifikation;KV/PV Basistarif privat;Kilometer (FWA);kein LSt.-Jahresausgleich;Arbeitskammer Pflicht;Sammelbeförderung;Arbeitszeitvereinbarung;Teilzeitfaktor;Entgeltfaktor;Teilzeit Grund;Funktion;Beschäftigung in;Tätigkeitsschlüssel;UV Zuordnung;Berechnungsmerkmal;Statistiktyp;Entgelt Art;§5 EntgFG: ärztliche AU-Feststellung spätestens am;Tarifart;Tarifgruppe;Tarifbasisdatum;Tarifstufe;Tarifstufe seit;Tarifgebiet;Tarifprozent;Ausschluss tarifl. Sonderzahlung;Urlaubsverwaltung;Arbeitsplatz lt. § 156 SGB IX;Arbeitszeitschlüssel für REHADAT;Schwerbehindert Pers.gruppe;Dienststelle;Ort Dienststelle;Aktenzeichen des Ausweises;Ausweis ab;Ausweis bis;Familienstand;Mutmaßlicher Entbindungstag;tats. Entbindungstag;Beschäftigungsverbot Beginn;Beschäftigungsverbot Ende;Beschäftigungsverbot Art;Schutzfrist Beginn;Schutzfrist Ende;Elternzeit Beginn;Elternzeit Ende;Elternzeit Art;Ordnungsmerkmal Wert 01;Ordnungsmerkmal Wert 02;Ordnungsmerkmal Wert 03;Ordnungsmerkmal Wert 04;Ordnungsmerkmal Wert 05;Ordnungsmerkmal Wert 06;Ordnungsmerkmal Wert 07;Ordnungsmerkmal Wert 08;Ordnungsmerkmal Wert 09;Ordnungsmerkmal Wert 10;Buchungsgruppencode;freier Text;Telefonnr. (privat);Mobiltelefonnr. (privat);E-Mail (privat);Telefonnr. (dienstl.);Mobiltelefonnr. (dienstl.);Faxnr. (dienstl.);E-Mail (dienstl.);E-Mail (E-Post;nationale ID;;ist Vers.-Bezug gem. §229;Beitragsabführungspflicht;Mehrfachbezug;max. beitragspfl. Vers.-Bezug;Beihilfe berechtigt;Zahlungszyklus;Aktenzeichen;;§ 168 SGB VI Leistungsträger WfbM;§ 179 SGB VI Leistungsträger WfbM;Heimkostenbeteiligung WfbM;;ATZ Vertrag vom;ATZ Beginn;ATZ Blockmodell Beginn;ATZ Freizeitphase Beginn;ATZ RV Prozent;ATZ Begrenzung ZBE;ATZ UB/ZBE bei Kr.-Geld;ATZ Aufstockung bei Kr.-Geld;ATZ Netto Prozent;ATZ Brutto Prozent;;Flex Beginn;Flex Ende;Flex Institut;Flex Vertragsnr WGH;Flex Vertragsnr. (WGH-AG);;KuG Leistungssatz;KuG Beginn;KuG Leistungsgruppe f. Grenzgänger;;Kasse (ZV);Vertragsbeginn ZV;Mitgliedsnr. in ZV;;Arbeitnehmergruppe;Winterb.-Umlage;Siko-Flex Meldung;;BV-Mitgliedsnummer;BV Selbszahler;;Aufwandspauschale;Funktionsbeschreibung;Von Mandant;KK Betriebsnummer;Tätigkeitscode Lfdnr.;Abschlagsbetrag');
        
        // Mapping-Definitionen für die wichtigsten Spalten
        // Format: column_index => [source_field, static_value, transform]
        $mappings = [
            0 => ['employee.employee_number', null, null],
            1 => ['contact.title', null, 'anrede'],
            2 => ['employee.title', null, null],
            14 => [null, '', null],
            15 => [null, 'Standard', null],
            16 => ['contract.start_date', null, 'date:d.m.Y'],
            27 => ['contract.personGroup.code', null, null],
            28 => [null, '1111', null],
            32 => ['employee.healthInsuranceCompany.ik_number', null, null],
            41 => ['employee.children_count', null, null],
            43 => [null, 'Ja', null], // Umlagepflicht U1 Lfz
            44 => [null, 'Ja', null], // Umlagepflicht U2
            45 => [null, 'Ja', null], // Umlagepflicht Insolvenz
            46 => ['employee.nationality', null, 'nationality:000'], // Staatsangehörigkeitsschlüssel
            51 => ['contract.employmentRelationship.code', null, null],
            94 => ['contract.activity_key', null, null],
            // ... weitere wichtige Mappings können später ergänzt werden
        ];

        $bar = $this->output->createProgressBar(199);
        $bar->start();

        // Alle 199 Spalten anlegen
        for ($i = 0; $i < 199; $i++) {
            $headerName = isset($headers[$i]) ? $headers[$i] : '';
            
            // Prüfe ob Mapping existiert
            $mapping = $mappings[$i] ?? [null, null, null];
            
            HcmExportTemplateColumn::create([
                'export_template_id' => $template->id,
                'column_index' => $i,
                'header_name' => $headerName,
                'source_field' => $mapping[0] ?? null,
                'static_value' => $mapping[1] ?? null,
                'transform' => $mapping[2] ?? null,
                'sort_order' => $i,
            ]);
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Template erfolgreich erstellt mit 199 Spalten!");
        $this->info("Template ID: {$template->id}");
        $this->info("Slug: {$template->slug}");

        return 0;
    }
}

