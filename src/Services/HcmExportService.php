<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmExport;
use Platform\Hcm\Models\HcmExportTemplate;
use Platform\Hcm\Models\HcmEmployer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class HcmExportService
{
    public function __construct(
        private int $teamId,
        private int $userId
    ) {}

    /**
     * Erstellt einen neuen Export
     */
    public function createExport(
        string $name,
        string $type,
        string $format = 'csv',
        ?int $templateId = null,
        ?array $parameters = null
    ): HcmExport {
        return HcmExport::create([
            'team_id' => $this->teamId,
            'created_by_user_id' => $this->userId,
            'name' => $name,
            'type' => $type,
            'format' => $format,
            'export_template_id' => $templateId,
            'parameters' => $parameters,
            'status' => 'pending',
        ]);
    }

    /**
     * Führt einen Export aus (flexible Basis-Methode)
     */
    public function executeExport(HcmExport $export): string
    {
        $export->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            // Basierend auf Export-Typ die entsprechende Methode aufrufen
            $filepath = match($export->type) {
                'infoniqa' => $this->exportInfoniqa($export),
                'payroll' => $this->exportPayroll($export),
                'employees' => $this->exportEmployees($export),
                'custom' => $this->exportCustom($export),
                default => throw new \InvalidArgumentException("Unbekannter Export-Typ: {$export->type}"),
            };

            $fileSize = Storage::disk('public')->size($filepath);
            $recordCount = $this->getRecordCount($export, $filepath);

            $export->update([
                'status' => 'completed',
                'completed_at' => now(),
                'file_path' => $filepath,
                'file_name' => basename($filepath),
                'file_size' => $fileSize,
                'record_count' => $recordCount,
            ]);

            return $filepath;
        } catch (\Throwable $e) {
            // Detaillierte Fehlermeldung mit Stack-Trace für Debugging
            $errorMessage = $e->getMessage() . "\n\n" . 
                          "File: " . $e->getFile() . ":" . $e->getLine() . "\n" .
                          "Trace:\n" . $e->getTraceAsString();
            
            $export->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);
            
            // Für Logging
            \Log::error('Export fehlgeschlagen', [
                'export_id' => $export->id,
                'type' => $export->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * INFONIQA-Export (komplexes Format mit mehreren Header-Zeilen)
     */
    private function exportInfoniqa(HcmExport $export): string
    {
        $filename = 'infoniqa_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        // employer_id aus Parameters holen
        $parameters = $export->parameters ?? [];
        $employerId = $parameters['employer_id'] ?? null;
        
        if (!$employerId) {
            throw new \InvalidArgumentException('INFONIQA-Export benötigt employer_id in den Parametern');
        }

        $employer = HcmEmployer::findOrFail($employerId);
        if ($employer->team_id !== $this->teamId) {
            throw new \InvalidArgumentException('Arbeitgeber gehört nicht zum aktuellen Team');
        }

        // Lade Mitarbeiter mit allen benötigten Relationen
        $employees = \Platform\Hcm\Models\HcmEmployee::with([
            'employer',
            'crmContactLinks.contact.postalAddresses',
            'crmContactLinks.contact.emailAddresses',
            'crmContactLinks.contact.phoneNumbers',
            'contracts' => function($q) {
                $q->where('is_active', true)
                  ->orderBy('start_date', 'desc');
            },
            'contracts.tariffGroup',
            'contracts.tariffLevel',
            'contracts.tariffAgreement',
            'contracts.insuranceStatus',
            'contracts.pensionType',
            'contracts.employmentRelationship',
            'contracts.personGroup',
            'contracts.primaryJobActivity',
            'contracts.jobActivityAlias',
            'contracts.taxClass',
            'contracts.costCenterLinks.costCenter',
            'healthInsuranceCompany',
            'payoutMethod',
        ])
        ->where('team_id', $this->teamId)
        ->where('employer_id', $employerId)
        ->where('is_active', true)
        ->get();

        $csvData = $this->generateInfoniqaCsv($employees, $employer);

        // UTF-8 BOM für Excel-Kompatibilität
        $csvData = "\xEF\xBB\xBF" . $csvData;
        
        Storage::disk('public')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * Generiert INFONIQA-CSV Format (mehrere Header-Zeilen)
     */
    private function generateInfoniqaCsv(Collection $employees, HcmEmployer $employer): string
    {
        $lines = [];
        $totalColumns = 199;
        
        // Zeile 1: Mandantennummer + "Konv Mitarbeiter" + leere Spalten + spezielle Werte
        $row1 = array_fill(0, $totalColumns, '');
        $row1[0] = $employer->employer_number ?? '';
        $row1[1] = 'Konv Mitarbeiter';
        // Ab Spalte ~75 kommen die speziellen Werte: "Fehlt im neuen KonvTool", "WfbM", "ATZ", "KUG", "ZVK", "Bau", "BV"
        $row1[74] = 'Fehlt im neuen KonvTool'; // Ungefähr bei Spalte 75
        $row1[90] = 'WfbM';
        $row1[91] = 'ATZ';
        $row1[92] = 'KUG';
        $row1[93] = 'ZVK';
        $row1[94] = 'Bau';
        $row1[95] = 'BV';
        $lines[] = $this->escapeCsvRow($row1);
        
        // Zeile 2: Kategorien (exakt nach Vorlage)
        $row2 = $this->getInfoniqaRow2();
        $lines[] = $this->escapeCsvRow($row2);
        
        // Zeile 3: Feldtypen (exakt nach Vorlage)
        $row3 = $this->getInfoniqaRow3();
        $lines[] = $this->escapeCsvRow($row3);
        
        // Zeile 4: Datentypen (exakt nach Vorlage)
        $row4 = $this->getInfoniqaRow4();
        $lines[] = $this->escapeCsvRow($row4);
        
        // Zeile 5: Spaltenüberschriften (exakt nach Vorlage)
        $row5 = $this->getInfoniqaHeaders();
        $lines[] = $this->escapeCsvRow($row5);
        
        // Datenzeilen
        foreach ($employees as $employee) {
            $contract = $employee->contracts->first();
            $lines[] = $this->generateInfoniqaEmployeeRow($employee, $contract, $totalColumns);
        }
        
        // Trailer-Zeile: Leere Spalten + "0000"
        $trailer = array_fill(0, $totalColumns, '');
        $trailer[13] = '0000'; // Ungefähr bei Spalte 14 (PLZ Code)
        $lines[] = $this->escapeCsvRow($trailer);
        
        return implode("\n", $lines);
    }

    /**
     * Zeile 2: Kategorien
     */
    private function getInfoniqaRow2(): array
    {
        return [
            'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein',
            'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein',
            'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein',
            'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein', 'Mitarbeiter Allgemein',
            'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1',
            'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1',
            'Mitarbeiter SV1', 'Mitarbeiter SV1', '',
            'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1', 'Mitarbeiter SV1',
            'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2', 'Mitarbeiter SV2',
            'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer',
            'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer',
            'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer', 'Mitarbeiter Steuer',
            'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung',
            'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung', 'Mitarbeiter Verwaltung',
            'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK',
            'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK', 'Mitarbeiter Tarif/ZVK',
            'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges',
            'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges',
            'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges', 'Mitarbeiter Sonstiges',
            'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation',
            'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation',
            'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation',
            'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', 'Mitarbeiter Kommunikation', '',
            'Mitarbeiter Betriebsrentner', 'Mitarbeiter Betriebsrentner', 'Mitarbeiter Betriebsrentner', 'Mitarbeiter Betriebsrentner', 'Mitarbeiter Betriebsrentner', 'Mitarbeiter Betriebsrentner', 'Mitarbeiter Betriebsrentner',
            'Anfangswerte', '', 'Mitarbeiter SV1 - WfbM', 'Mitarbeiter SV1 - WfbM', 'Mitarbeiter SV1 - WfbM', '',
            'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX',
            'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', '',
            'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', 'Mitarbeiter ATZ/FLEX', '', '',
            'Mitarbeiter Steuer - KuG', 'Mitarbeiter Steuer - KuG', 'Mitarbeiter Steuer - KuG', '', '',
            'Mitarbeiter Tarif/ZVK - Zusatzversorgung', 'Mitarbeiter Tarif/ZVK - Zusatzversorgung', 'Mitarbeiter Tarif/ZVK - Zusatzversorgung', '', '',
            'Mitarbeiter Tarif/ZVK - Baulohn', 'Mitarbeiter Tarif/ZVK - Baulohn', 'Mitarbeiter Tarif/ZVK - Baulohn', '', '',
            'Berufsständisch Versicherte AN', 'Berufsständisch Versicherte AN', '', '',
            'Steuer - Aufwandspauschale', 'Verwaltung', 'spezielle Felder', 'spezielle Felder', 'spezielle Felder',
        ];
    }

    /**
     * Zeile 3: Feldtypen
     */
    private function getInfoniqaRow3(): array
    {
        // Exakt nach Vorlage - die Zahlen aus Zeile 3
        $values = explode(';', '1;8;5;7;2;6;3;21;25;26;27;29;22;23;313;310;300;301;302;304;321;332;306;312;305;307;333;102;101;125;105;134;110;120;126;106;127;107;128;108;117;192;123;142;143;144;104;;136;137;138;140;146;440;145;100;9;10;11;12;13;14;132;112;118;218;244;246;203;243;240;241;200;208;201;204;205;206;207;223;224;245;121;202;209;221;222;320;323;324;322;410;191;190;401;311;315;314;820;500;501;507;502;503;504;513;514;512;420;421;426;427;422;423;424;425;18;650;651;657;658;659;655;656;660;661;662;350;351;317;392;393;806;807;808;809;810;349;318;41;42;46;43;49;44;47;48;211;;178;180;181;182;185;189;;;171;177;170;;150;153;154;155;151;156;157;158;152;159;;167;168;169;165;166;;220;228;229;;590;591;592;;601;602;604;;198;197;;214;49978;5485000;5005;5504;49996');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 4: Datentypen
     */
    private function getInfoniqaRow4(): array
    {
        // Exakt nach Vorlage - die Datentypen aus Zeile 4
        $values = explode(';', 'Code10;Text15;Text20;Text15;Text30;Text15;Text30;Text30;Code10;Text10;Text30;Code3;Code10;Text30;Code10;Code10;Date;Date;Code20;Date;Code20;Date;Date;Date;Date;Date;Date;Code3;Code4;Option;Option;Option;Code20;Decimal;Option;Option;Option;Option;Option;Option;Option;Option;Decimal;Boolean;Boolean;Boolean;Code3;;Date;Date;Option;Boolean;Code10;Date;Boolean;Code12;Option;Date;Text30;Text30;Text15;Text15;Code3;Code20;Option;Option;Option;Option;Code11;Code10;Boolean;Option;Option;Decimal;Decimal;Decimal;Decimal;Decimal;Decimal;Code20;Code20;Option;Decimal;Integer;Boolean;Option;Option;Code10;Decimal;Decimal;Code20;Code20;Option;Code9;Code10;Code20;Code20;Option;Option;Code20;Code20;Date;Code20;Date;Code20;Decimal;Option;Option;Option;Option;Code20;Code20;Text50;Text30;Date;Date;Option;Date;Date;Date;Date;Option;Date;Date;Date;Date;Option;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code10;Text30;Text30;Text30;Text80;Text30;Text30;Text30;Text80;Text80;Test20;;Option;Option;Option;Decimal;Boolean;Option;Code20;;Code20;Code20;Boolean;;Date;Date;Date;Date;Decimal;Decimal;Boolean;Boolean;Decimal;Decimal;;Date;Date;Code20;Text30;Text30;;Option;Date;Option;;Code20;Date;Code20;;Option;Option;Boolean;;Code17;Boolean;;Option;Text200;Text30;Code10;Integer;Decimal');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 5: Header
     */
    private function getInfoniqaHeaders(): array
    {
        return [
            'Nr.', 'Anrede', 'Titel', 'Namenszusatz', 'Vorname', 'Namensvorsatz', 'Name',
            'Straße', 'Hausnr.', 'Hausnr.-zusatz', 'Adresszusatz', 'Länderkennzeichen', 'PLZ Code', 'Ort',
            'Betriebsteilcode', 'Abrechnungskreis', 'Eintrittsdatum', 'Austrittsdatum', 'Austrittsgrund',
            'Vertragsende', 'Befristungsgrund', 'Ende Probezeit', 'Entlassung / Kündigung am',
            'unwiderrufliche Freistellung am', 'Betriebszugehörigkeit seit', 'Berufserf./Ausbildung seit',
            'Ende der Ausbildung', 'Personengruppenschlüssel', 'Beitragsgruppe',
            'BYGR KV', 'KV-Kennzeichen', 'KV Vertragsart', 'KK-Code (Einzugsstelle)', 'KV-Beitrag privat',
            'BYGR RV', 'RV-Kennzeichen', 'BYGR AV', 'AV-Kennzeichen', 'BYGR PV', 'PV-Kennzeichen',
            'Kinder', 'Kinder unter 25 Jahren für PV-Abschlag', 'PV-Beitrag privat',
            'Umlagepflicht U1 Lfz', 'Umlagepflicht U2', 'Umlagepflicht Insolvenz', 'Staatsangehörigkeitsschlüssel',
            '', 'Rentenbeginn', 'Befreiung von RV-Pflicht ab (§ 6 Abs.1b SGBVI)', 'Beschäftigungsverhältnis',
            'Mehrfachbeschäftigt', 'Rentenart', 'Altersrente beantragt am', 'Saisonarbeitnehmer', 'Soz.-Versicherungsnr.',
            'Geschlecht', 'Geburtsdatum', 'Geburtsname', 'Geburtsort', 'Namenszusatz Geburtsname', 'Namensvorsatz Geburtsname',
            'Geburtsland', 'Krankenkasse (tats.)', 'PGR 109/110:Versicherungsstatus', 'steuerpflichtig EStG § 1',
            'Art der Besteuerung', 'wer trägt die Steuer', 'Identifikationsnummer', 'abw. Geburtsdatum lt. Pass',
            'Haupt AG (Steuer)', 'Herkunft LSt.-Merkmale gem. EStG', 'Steuerklasse', 'Faktor nach § 39f EStG',
            'Kinderfreibetrag', 'Freibetrag Monat', 'Freibetrag Jahr', 'Hinzurechnung Monat', 'Hinzurechnung Jahr',
            'Konfession', 'Konfession Ehepartner', 'Lohnsteuerspezifikation', 'KV/PV Basistarif privat', 'Kilometer (FWA)',
            'kein LSt.-Jahresausgleich', 'Arbeitskammer Pflicht', 'Sammelbeförderung', 'Arbeitszeitvereinbarung',
            'Teilzeitfaktor', 'Entgeltfaktor', 'Teilzeit Grund', 'Funktion', 'Beschäftigung in', 'Tätigkeitsschlüssel',
            'UV Zuordnung', 'Berechnungsmerkmal', 'Statistiktyp', 'Entgelt Art',
            '§5 EntgFG: ärztliche AU-Feststellung spätestens am', 'Tarifart', 'Tarifgruppe', 'Tarifbasisdatum', 'Tarifstufe',
            'Tarifstufe seit', 'Tarifgebiet', 'Tarifprozent', 'Ausschluss tarifl. Sonderzahlung', 'Urlaubsverwaltung',
            'Arbeitsplatz lt. § 156 SGB IX', 'Arbeitszeitschlüssel für REHADAT', 'Schwerbehindert Pers.gruppe',
            'Dienststelle', 'Ort Dienststelle', 'Aktenzeichen des Ausweises', 'Ausweis ab', 'Ausweis bis',
            'Familienstand', 'Mutmaßlicher Entbindungstag', 'tats. Entbindungstag', 'Beschäftigungsverbot Beginn',
            'Beschäftigungsverbot Ende', 'Beschäftigungsverbot Art', 'Schutzfrist Beginn', 'Schutzfrist Ende',
            'Elternzeit Beginn', 'Elternzeit Ende', 'Elternzeit Art',
            'Ordnungsmerkmal Wert 01', 'Ordnungsmerkmal Wert 02', 'Ordnungsmerkmal Wert 03', 'Ordnungsmerkmal Wert 04',
            'Ordnungsmerkmal Wert 05', 'Ordnungsmerkmal Wert 06', 'Ordnungsmerkmal Wert 07', 'Ordnungsmerkmal Wert 08',
            'Ordnungsmerkmal Wert 09', 'Ordnungsmerkmal Wert 10',
            'Buchungsgruppencode', 'freier Text', 'Telefonnr. (privat)', 'Mobiltelefonnr. (privat)', 'E-Mail (privat)',
            'Telefonnr. (dienstl.)', 'Mobiltelefonnr. (dienstl.)', 'Faxnr. (dienstl.)', 'E-Mail (dienstl.)', 'E-Mail (E-Post',
            'nationale ID', '', 'ist Vers.-Bezug gem. §229', 'Beitragsabführungspflicht', 'Mehrfachbezug',
            'max. beitragspfl. Vers.-Bezug', 'Beihilfe berechtigt', 'Zahlungszyklus', 'Aktenzeichen',
            '', '§ 168 SGB VI Leistungsträger WfbM', '§ 179 SGB VI Leistungsträger WfbM', 'Heimkostenbeteiligung WfbM',
            '', 'ATZ Vertrag vom', 'ATZ Beginn', 'ATZ Blockmodell Beginn', 'ATZ Freizeitphase Beginn', 'ATZ RV Prozent',
            'ATZ Begrenzung ZBE', 'ATZ UB/ZBE bei Kr.-Geld', 'ATZ Aufstockung bei Kr.-Geld', 'ATZ Netto Prozent', 'ATZ Brutto Prozent',
            '', 'Flex Beginn', 'Flex Ende', 'Flex Institut', 'Flex Vertragsnr WGH', 'Flex Vertragsnr. (WGH-AG)',
            '', '', 'KuG Leistungssatz', 'KuG Beginn', 'KuG Leistungsgruppe f. Grenzgänger',
            '', '', 'Kasse (ZV)', 'Vertragsbeginn ZV', 'Mitgliedsnr. in ZV',
            '', '', 'Arbeitnehmergruppe', 'Winterb.-Umlage', 'Siko-Flex Meldung',
            '', '', 'BV-Mitgliedsnummer', 'BV Selbszahler',
            '', '', 'Aufwandspauschale', 'Funktionsbeschreibung', 'Von Mandant', 'KK Betriebsnummer', 'Tätigkeitscode Lfdnr.', 'Abschlagsbetrag',
        ];
    }

    /**
     * Generiert eine Mitarbeiter-Zeile im INFONIQA-Format
     */
    private function generateInfoniqaEmployeeRow($employee, $contract, int $totalColumns): string
    {
        $contact = $employee->crmContactLinks->first()?->contact;
        $address = $contact?->addresses->first();
        $primaryPhone = $contact?->phoneNumbers->first();
        $primaryEmail = $contact?->emailAddresses->first();
        $costCenter = $contract?->getCostCenter();
        
        $row = array_fill(0, $totalColumns, '');
        
        // Spalte 1-7: Grunddaten
        $row[0] = $employee->employee_number ?? '';
        $row[1] = $contact?->title ?? ''; // Anrede
        $row[2] = ''; // Titel
        $row[3] = ''; // Namenszusatz
        $row[4] = $contact?->first_name ?? '';
        $row[5] = ''; // Namensvorsatz
        $row[6] = $contact?->last_name ?? '';
        
        // Spalte 8-14: Adresse
        $row[7] = $address?->street ?? '';
        $row[8] = $address?->house_number ?? '';
        $row[9] = ''; // Hausnr.-zusatz
        $row[10] = ''; // Adresszusatz
        $row[11] = $address?->country ?? 'DE';
        $row[12] = $address?->postal_code ?? '';
        $row[13] = $address?->city ?? '';
        
        // Spalte 15-16: Betrieb
        $row[14] = $employee->employer?->employer_number ?? '';
        $row[15] = is_object($costCenter) ? ($costCenter->code ?? '') : ($costCenter ?? '');
        
        // Spalte 17-27: Vertragsdaten
        $row[16] = $contract?->start_date?->format('d.m.Y') ?? '';
        $row[17] = $contract?->end_date?->format('d.m.Y') ?? '';
        $row[18] = ''; // Austrittsgrund
        $row[19] = $contract?->end_date?->format('d.m.Y') ?? '';
        $row[20] = ''; // Befristungsgrund
        $row[21] = $contract?->probation_end_date?->format('d.m.Y') ?? '';
        $row[22] = ''; // Entlassung
        $row[23] = ''; // Freistellung
        $row[24] = $contract?->start_date?->format('d.m.Y') ?? '';
        $row[25] = ''; // Berufserfahrung seit
        $row[26] = ''; // Ausbildung Ende
        
        // Spalte 28-29: Personengruppe
        $row[27] = $contract?->personGroup?->code ?? '';
        $row[28] = ''; // Beitragsgruppe
        
        // Spalte 30-40: Sozialversicherung
        $row[29] = ''; // BYGR KV
        $row[30] = ''; // KV-Kennzeichen
        $row[31] = ''; // KV Vertragsart
        $row[32] = $employee->healthInsuranceCompany?->ik_number ?? '';
        $row[33] = ''; // KV-Beitrag privat
        $row[34] = ''; // BYGR RV
        $row[35] = ''; // RV-Kennzeichen
        $row[36] = ''; // BYGR AV
        $row[37] = ''; // AV-Kennzeichen
        $row[38] = ''; // BYGR PV
        $row[39] = ''; // PV-Kennzeichen
        
        // Spalte 41-47: Kinder & Umlagen
        $row[40] = (string)($employee->children_count ?? 0);
        $row[41] = ''; // Kinder unter 25
        $row[42] = ''; // PV-Beitrag privat
        $row[43] = $contract?->levy_u1 ? 'Ja' : 'Nein';
        $row[44] = $contract?->levy_u2 ? 'Ja' : 'Nein';
        $row[45] = $contract?->levy_insolvency ? 'Ja' : 'Nein';
        $row[46] = ''; // Staatsangehörigkeitsschlüssel
        
        // Spalte 51-55: Beschäftigung
        $row[50] = $contract?->employmentRelationship?->code ?? '';
        $row[51] = ''; // Mehrfachbeschäftigt
        $row[52] = $contract?->pensionType?->code ?? '';
        $row[53] = ''; // Altersrente beantragt
        $row[54] = ''; // Saisonarbeitnehmer
        
        // Spalte 56-57: SV-Nummer & Geschlecht
        $row[55] = $contract?->social_security_number ?? ''; // Entschlüsselt durch Cast
        $row[56] = $contact?->gender === 'male' ? 'Männlich' : ($contact?->gender === 'female' ? 'Weiblich' : '');
        
        // Spalte 58-63: Geburtsdaten
        $row[57] = $contact?->birth_date?->format('d.m.Y') ?? '';
        $row[58] = $contact?->last_name ?? ''; // Geburtsname
        $row[59] = $contact?->birth_place ?? '';
        $row[60] = ''; // Namenszusatz Geburtsname
        $row[61] = ''; // Namensvorsatz Geburtsname
        $row[62] = ''; // Geburtsland
        
        // Spalte 64-65: Krankenkasse
        $row[63] = $employee->healthInsuranceCompany?->name ?? '';
        $row[64] = $contract?->insuranceStatus?->code ?? '';
        
        // Spalte 66-73: Steuer
        $row[65] = 'unbeschränkt'; // steuerpflichtig EStG § 1
        $row[66] = 'individuell'; // Art der Besteuerung
        $row[67] = ''; // wer trägt die Steuer
        $row[68] = $employee->tax_id_number ?? '';
        $row[69] = ''; // abw. Geburtsdatum
        $row[70] = ''; // Haupt AG (Steuer)
        $row[71] = ''; // Herkunft LSt.-Merkmale
        $row[72] = $contract?->taxClass?->code ?? '';
        $row[73] = ''; // Faktor
        
        // Spalte 89-92: Arbeitszeit
        $row[88] = $contract?->working_hours_per_week ?? '';
        $row[89] = $contract?->part_time_factor ?? '';
        $row[90] = $contract?->wage_factor ?? '';
        $row[91] = ''; // Teilzeit Grund
        
        // Spalte 93-94: Funktion & Beschäftigung
        $jobTitle = $contract?->jobTitles->first();
        $row[92] = $jobTitle?->name ?? '';
        $row[93] = 'Firma';
        
        // Spalte 94: Tätigkeitsschlüssel (9-stellig)
        $activityCode = $contract?->primaryJobActivity?->code ?? '';
        $activityKey = $contract?->activity_key_1 ?? ''; // Erste 5 Ziffern
        $activityLevel = $contract?->activity_level_1 ?? '';
        $fullActivityKey = str_pad($activityKey . $activityCode . $activityLevel, 9, '0', STR_PAD_LEFT);
        $row[93] = $fullActivityKey; // Tätigkeitsschlüssel (Spalte 94, Index 93)
        
        // Spalte 95-96: UV & Berechnung
        $row[94] = ''; // UV Zuordnung
        $row[95] = ''; // Berechnungsmerkmal
        
        // Spalte 100-104: Tarif
        $row[99] = $contract?->tariffAgreement?->name ?? '';
        $row[100] = $contract?->tariffGroup?->code ?? '';
        $row[101] = $contract?->tariff_base_date?->format('d.m.Y') ?? '';
        $row[102] = $contract?->tariffLevel?->level ?? '';
        $row[103] = $contract?->tariff_level_since?->format('d.m.Y') ?? '';
        
        // Spalte 107-108: Urlaub
        $row[106] = ''; // Urlaubsverwaltung
        $row[107] = ''; // Arbeitsplatz lt. § 156
        
        // Spalte 111-114: Dienststelle & Ort
        $row[110] = is_object($costCenter) ? ($costCenter->name ?? '') : '';
        $row[111] = is_object($costCenter) ? ($costCenter->name ?? '') : '';
        $row[112] = ''; // Aktenzeichen
        $row[113] = ''; // Ausweis ab
        $row[114] = ''; // Ausweis bis
        
        // Spalte 115: Familienstand
        $row[114] = $contact?->marital_status ?? '';
        
        // Spalte 145-149: Kommunikation
        $row[144] = $primaryPhone?->number ?? ''; // Telefonnr. (privat)
        $row[145] = $primaryPhone?->number ?? ''; // Mobiltelefonnr. (privat)
        $row[146] = $primaryEmail?->email ?? ''; // E-Mail (privat)
        $row[147] = ''; // Telefonnr. (dienstl.)
        $row[148] = ''; // Mobiltelefonnr. (dienstl.)
        
        return $this->escapeCsvRow($row);
    }

    /**
     * Payroll-Export (einfacher)
     */
    private function exportPayroll(HcmExport $export): string
    {
        // Verwendet den bestehenden PayrollTypeExportService
        $service = new PayrollTypeExportService($this->teamId);
        return $service->exportToCsv();
    }

    /**
     * Mitarbeiter-Export (einfach)
     */
    private function exportEmployees(HcmExport $export): string
    {
        $filename = 'employees_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = 'exports/hcm/' . $filename;

        $employees = \Platform\Hcm\Models\HcmEmployee::with(['employer', 'contracts'])
            ->where('team_id', $this->teamId)
            ->get();

        $headers = ['Mitarbeiternummer', 'Nachname', 'Vorname', 'Arbeitgeber', 'Status'];
        $csvData = [];
        $csvData[] = $this->escapeCsvRow($headers);

        foreach ($employees as $employee) {
            $contact = $employee->crmContactLinks->first()?->contact;
            $csvData[] = $this->escapeCsvRow([
                $employee->employee_number,
                $contact?->last_name ?? '',
                $contact?->first_name ?? '',
                $employee->employer?->name ?? '',
                $employee->is_active ? 'Aktiv' : 'Inaktiv',
            ]);
        }

        $csvData = "\xEF\xBB\xBF" . implode("\n", $csvData);
        Storage::disk('public')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * Custom-Export (basierend auf Template)
     */
    private function exportCustom(HcmExport $export): string
    {
        if (!$export->template) {
            throw new \InvalidArgumentException('Custom Export benötigt ein Template');
        }

        $config = $export->template->configuration;
        // Hier würde die Template-basierte Logik implementiert werden
        
        throw new \RuntimeException('Custom Export noch nicht implementiert');
    }

    /**
     * Hilfsmethode: CSV-Zeile escapen
     */
    private function escapeCsvRow(array $row): string
    {
        $escapedRow = [];
        foreach ($row as $field) {
            $field = trim((string) $field);
            $field = str_replace('"', '""', $field);
            
            if (str_contains($field, ';') || str_contains($field, '"') || 
                str_contains($field, "\n") || str_contains($field, "\r")) {
                $escapedRow[] = '"' . $field . '"';
            } else {
                $escapedRow[] = $field;
            }
        }
        return implode(';', $escapedRow);
    }

    /**
     * Ermittelt die Anzahl der Datensätze im Export
     */
    private function getRecordCount(HcmExport $export, string $filepath): int
    {
        // Vereinfacht: Zähle Zeilen in CSV (ohne Header)
        $content = Storage::disk('public')->get($filepath);
        $lines = explode("\n", $content);
        return max(0, count($lines) - 6); // Minus Header-Zeilen (5) und Trailer (1)
    }
}
