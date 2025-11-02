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

            $fileSize = Storage::disk('local')->size($filepath);
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
            'contracts.tariffGroup.tariffAgreement',
            'contracts.tariffLevel',
            'contracts.insuranceStatus',
            'contracts.pensionType',
            'contracts.employmentRelationship',
            'contracts.personGroup',
            'contracts.primaryJobActivity',
            'contracts.jobActivityAlias',
            'contracts.jobTitles',
            'contracts.taxClass',
            'contracts.costCenterLinks.costCenter',
            'contracts.levyTypes',
            'healthInsuranceCompany',
            'payoutMethod',
            'benefits' => function($q) {
                $q->where('benefit_type', 'bkv')
                  ->where('is_active', true);
            },
        ])
        ->where('team_id', $this->teamId)
        ->where('employer_id', $employerId)
        ->where('is_active', true)
        ->get();

        $csvData = $this->generateInfoniqaCsv($employees, $employer);

        // UTF-8 BOM für Excel-Kompatibilität
        $csvData = "\xEF\xBB\xBF" . $csvData;
        
        // Exports in geschütztem Storage-Verzeichnis speichern (nicht public)
        Storage::disk('local')->put($filepath, $csvData);

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
        $row1[0] = (string)($employer->employer_number ?? '');
        $row1[1] = 'Konv Mitarbeiter';
        $row1[73] = 'Fehlt im neuen KonvTool'; // Position 74 (Index 73)
        $row1[89] = 'WfbM'; // Position 90
        $row1[90] = 'ATZ'; // Position 91
        $row1[91] = 'KUG'; // Position 92
        $row1[92] = 'ZVK'; // Position 93
        $row1[93] = 'Bau'; // Position 94
        $row1[94] = 'BV'; // Position 95
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
            foreach ($employee->contracts as $contract) {
                $lines[] = $this->generateInfoniqaEmployeeRow($employee, $contract, $totalColumns);
            }
        }
        
        // Trailer-Zeile: Leere Spalten + "0000"
        $trailer = array_fill(0, $totalColumns, '');
        $trailer[12] = '0000'; // PLZ Code (Spalte 13, Index 12)
        $lines[] = $this->escapeCsvRow($trailer);
        
        return implode("\n", $lines);
    }

    /**
     * Zeile 2: Kategorien
     */
    private function getInfoniqaRow2(): array
    {
        // Exakt aus der CSV-Vorlage kopiert
        $values = explode(';', 'Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter Allgemein;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV1;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter SV2;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Steuer;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Verwaltung;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Tarif/ZVK;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Sonstiges;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;Mitarbeiter Kommunikation;;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Mitarbeiter Betriebsrentner;Anfangswerte;;Mitarbeiter SV1 - WfbM;Mitarbeiter SV1 - WfbM;Mitarbeiter SV1 - WfbM;;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;Mitarbeiter ATZ/FLEX;;Mitarbeiter Steuer - KuG;Mitarbeiter Steuer - KuG;Mitarbeiter Steuer - KuG;;Mitarbeiter Tarif/ZVK - Zusatzversorgung;Mitarbeiter Tarif/ZVK - Zusatzversorgung;Mitarbeiter Tarif/ZVK - Zusatzversorgung;;Mitarbeiter Tarif/ZVK - Baulohn;Mitarbeiter Tarif/ZVK - Baulohn;Mitarbeiter Tarif/ZVK - Baulohn;;Berufsständisch Versicherte AN;Berufsständisch Versicherte AN;;Steuer - Aufwandspauschale;Verwaltung;spezielle Felder;spezielle Felder;spezielle Felder');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 3: Feldtypen
     */
    private function getInfoniqaRow3(): array
    {
        $values = explode(';', '1;8;5;7;2;6;3;21;25;26;27;29;22;23;313;310;300;301;302;304;321;332;306;312;305;307;333;102;101;125;105;134;110;120;126;106;127;107;128;108;117;192;123;142;143;144;104;;136;137;138;140;146;440;145;100;9;10;11;12;13;14;132;112;118;218;244;246;203;243;240;241;200;208;201;204;205;206;207;223;224;245;121;202;209;221;222;320;323;324;322;410;191;190;401;311;315;314;820;500;501;507;502;503;504;513;514;512;420;421;426;427;422;423;424;425;18;650;651;657;658;659;655;656;660;661;662;350;351;317;392;393;806;807;808;809;810;349;318;41;42;46;43;49;44;47;48;211;;178;180;181;182;185;189;;;171;177;170;;150;153;154;155;151;156;157;158;152;159;;167;168;169;165;166;;220;228;229;;590;591;592;;601;602;604;;198;197;;214;49978;5485000;5005;5504;49996');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 4: Datentypen
     */
    private function getInfoniqaRow4(): array
    {
        $values = explode(';', 'Code10;Text15;Text20;Text15;Text30;Text15;Text30;Text30;Code10;Text10;Text30;Code3;Code10;Text30;Code10;Code10;Date;Date;Code20;Date;Code20;Date;Date;Date;Date;Date;Date;Code3;Code4;Option;Option;Option;Code20;Decimal;Option;Option;Option;Option;Option;Option;Option;Option;Decimal;Boolean;Boolean;Boolean;Code3;;Date;Date;Option;Boolean;Code10;Date;Boolean;Code12;Option;Date;Text30;Text30;Text15;Text15;Code3;Code20;Option;Option;Option;Option;Code11;Code10;Boolean;Option;Option;Decimal;Decimal;Decimal;Decimal;Decimal;Decimal;Code20;Code20;Option;Decimal;Integer;Boolean;Option;Option;Code10;Decimal;Decimal;Code20;Code20;Option;Code9;Code10;Code20;Code20;Option;Option;Code20;Code20;Date;Code20;Date;Code20;Decimal;Option;Option;Option;Option;Code20;Code20;Text50;Text30;Date;Date;Option;Date;Date;Date;Date;Option;Date;Date;Date;Date;Option;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code20;Code10;Text30;Text30;Text30;Text80;Text30;Text30;Text30;Text80;Text80;Test20;;Option;Option;Option;Decimal;Boolean;Option;Code20;;Code20;Code20;Boolean;;Date;Date;Date;Date;Decimal;Decimal;Boolean;Boolean;Decimal;Decimal;;Date;Date;Code20;Text30;Text30;;Option;Date;Option;;Code20;Date;Code20;;Option;Option;Boolean;;Code17;Boolean;;Option;Text200;Text30;Code10;Integer;Decimal');
        return array_pad($values, 199, '');
    }

    /**
     * Zeile 5: Header
     */
    private function getInfoniqaHeaders(): array
    {
        $values = explode(';', 'Nr.;Anrede;Titel;Namenszusatz;Vorname;Namensvorsatz;Name;Straße;Hausnr.;Hausnr.-zusatz;Adresszusatz;Länderkennzeichen;PLZ Code;Ort;Betriebsteilcode;Abrechnungskreis;Eintrittsdatum;Austrittsdatum;Austrittsgrund;Vertragsende;Befristungsgrund;Ende Probezeit;Entlassung / Kündigung am;unwiderrufliche Freistellung am;Betriebszugehörigkeit seit;Berufserf./Ausbildung seit;Ende der Ausbildung;Personengruppenschlüssel;Beitragsgruppe;BYGR KV;KV-Kennzeichen;KV Vertragsart;KK-Code (Einzugsstelle);KV-Beitrag privat;BYGR RV;RV-Kennzeichen;BYGR AV;AV-Kennzeichen;BYGR PV;PV-Kennzeichen;Kinder;Kinder unter 25 Jahren für PV-Abschlag;PV-Beitrag privat;Umlagepflicht U1 Lfz;Umlagepflicht U2;Umlagepflicht Insolvenz;Staatsangehörigkeitsschlüssel;;Rentenbeginn;Befreiung von RV-Pflicht ab (§ 6 Abs.1b SGBVI);Beschäftigungsverhältnis;Mehrfachbeschäftigt;Rentenart;Altersrente beantragt am;Saisonarbeitnehmer;Soz.-Versicherungsnr.;Geschlecht;Geburtsdatum;Geburtsname;Geburtsort;Namenszusatz Geburtsname;Namensvorsatz Geburtsname;Geburtsland;Krankenkasse (tats.);PGR 109/110:Versicherungsstatus;steuerpflichtig EStG § 1;Art der Besteuerung;wer trägt die Steuer;Identifikationsnummer;abw. Geburtsdatum lt. Pass;Haupt AG (Steuer);Herkunft LSt.-Merkmale gem. EStG;Steuerklasse;Faktor nach § 39f EStG;Kinderfreibetrag;Freibetrag Monat;Freibetrag Jahr;Hinzurechnung Monat;Hinzurechnung Jahr;Konfession;Konfession Ehepartner;Lohnsteuerspezifikation;KV/PV Basistarif privat;Kilometer (FWA);kein LSt.-Jahresausgleich;Arbeitskammer Pflicht;Sammelbeförderung;Arbeitszeitvereinbarung;Teilzeitfaktor;Entgeltfaktor;Teilzeit Grund;Funktion;Beschäftigung in;Tätigkeitsschlüssel;UV Zuordnung;Berechnungsmerkmal;Statistiktyp;Entgelt Art;§5 EntgFG: ärztliche AU-Feststellung spätestens am;Tarifart;Tarifgruppe;Tarifbasisdatum;Tarifstufe;Tarifstufe seit;Tarifgebiet;Tarifprozent;Ausschluss tarifl. Sonderzahlung;Urlaubsverwaltung;Arbeitsplatz lt. § 156 SGB IX;Arbeitszeitschlüssel für REHADAT;Schwerbehindert Pers.gruppe;Dienststelle;Ort Dienststelle;Aktenzeichen des Ausweises;Ausweis ab;Ausweis bis;Familienstand;Mutmaßlicher Entbindungstag;tats. Entbindungstag;Beschäftigungsverbot Beginn;Beschäftigungsverbot Ende;Beschäftigungsverbot Art;Schutzfrist Beginn;Schutzfrist Ende;Elternzeit Beginn;Elternzeit Ende;Elternzeit Art;Ordnungsmerkmal Wert 01;Ordnungsmerkmal Wert 02;Ordnungsmerkmal Wert 03;Ordnungsmerkmal Wert 04;Ordnungsmerkmal Wert 05;Ordnungsmerkmal Wert 06;Ordnungsmerkmal Wert 07;Ordnungsmerkmal Wert 08;Ordnungsmerkmal Wert 09;Ordnungsmerkmal Wert 10;Buchungsgruppencode;freier Text;Telefonnr. (privat);Mobiltelefonnr. (privat);E-Mail (privat);Telefonnr. (dienstl.);Mobiltelefonnr. (dienstl.);Faxnr. (dienstl.);E-Mail (dienstl.);E-Mail (E-Post;nationale ID;;ist Vers.-Bezug gem. §229;Beitragsabführungspflicht;Mehrfachbezug;max. beitragspfl. Vers.-Bezug;Beihilfe berechtigt;Zahlungszyklus;Aktenzeichen;;§ 168 SGB VI Leistungsträger WfbM;§ 179 SGB VI Leistungsträger WfbM;Heimkostenbeteiligung WfbM;;ATZ Vertrag vom;ATZ Beginn;ATZ Blockmodell Beginn;ATZ Freizeitphase Beginn;ATZ RV Prozent;ATZ Begrenzung ZBE;ATZ UB/ZBE bei Kr.-Geld;ATZ Aufstockung bei Kr.-Geld;ATZ Netto Prozent;ATZ Brutto Prozent;;Flex Beginn;Flex Ende;Flex Institut;Flex Vertragsnr WGH;Flex Vertragsnr. (WGH-AG);;KuG Leistungssatz;KuG Beginn;KuG Leistungsgruppe f. Grenzgänger;;Kasse (ZV);Vertragsbeginn ZV;Mitgliedsnr. in ZV;;Arbeitnehmergruppe;Winterb.-Umlage;Siko-Flex Meldung;;BV-Mitgliedsnummer;BV Selbszahler;;Aufwandspauschale;Funktionsbeschreibung;Von Mandant;KK Betriebsnummer;Tätigkeitscode Lfdnr.;Abschlagsbetrag');
        return array_pad($values, 199, '');
    }

    /**
     * Generiert eine Mitarbeiter-Zeile im INFONIQA-Format
     */
    private function generateInfoniqaEmployeeRow($employee, $contract, int $totalColumns): string
    {
        $contact = $employee->crmContactLinks->first()?->contact;
        $address = $contact?->postalAddresses->first();
        $primaryPhone = $contact?->phoneNumbers->where('type', 'mobile')->first() ?? $contact?->phoneNumbers->first();
        $primaryEmail = $contact?->emailAddresses->first();
        $costCenter = $contract?->getCostCenter();
        
        $row = array_fill(0, $totalColumns, '');
        
        // 1. Nr. (Mitarbeiternummer)
        $row[0] = (string)($employee->employee_number ?? '');
        
        // 2. Anrede
        $title = $contact?->title ?? '';
        if ($title === 'Herr' || $title === 'Frau') {
            $row[1] = $title;
        } elseif ($employee->gender === 'male' || $contact?->gender === 'male') {
            $row[1] = 'Herr';
        } elseif ($employee->gender === 'female' || $contact?->gender === 'female') {
            $row[1] = 'Frau';
        }
        
        // 3. Titel
        // 4. Namenszusatz
        // 5. Vorname
        $row[4] = $contact?->first_name ?? '';
        
        // 6. Namensvorsatz
        // 7. Name
        $row[6] = $contact?->last_name ?? '';
        
        // 8-14. Adresse
        if ($address) {
            $row[7] = $address->street ?? '';
            $row[8] = $address->house_number ?? '';
            $row[11] = $address->country ?? 'DE';
            $row[12] = $address->postal_code ?? '';
            $row[13] = $address->city ?? '';
        }
        
        // 15. Betriebsteilcode (leer lassen)
        $row[14] = '';
        
        // 16. Abrechnungskreis (immer "Standard")
        $row[15] = 'Standard';
        
        // 17. Eintrittsdatum
        $row[16] = $contract?->start_date?->format('d.m.Y') ?? '';
        
        // 18. Austrittsdatum
        $row[17] = $contract?->end_date?->format('d.m.Y') ?? '';
        
        // 20. Vertragsende
        $row[19] = $contract?->end_date?->format('d.m.Y') ?? '';
        
        // 22. Ende Probezeit
        $row[21] = $contract?->probation_end_date?->format('d.m.Y') ?? '';
        
        // 25. Betriebszugehörigkeit seit
        $row[24] = $contract?->start_date?->format('d.m.Y') ?? '';
        
        // 28. Personengruppenschlüssel
        $row[27] = $contract?->personGroup?->code ?? '';
        
        // 29. Beitragsgruppe (immer "1111")
        $row[28] = '1111';
        
        // 30. BYGR KV (Index 29)
        // TODO: Später aus Tarif-Lookup
        $row[29] = '';
        
        // 31. KV-Kennzeichen (Index 30)
        // TODO: Später aus Tarif-Lookup
        $row[30] = '';
        
        // 32. KV Vertragsart (Index 31)
        // "1" wenn privat versichert (BKV vorhanden), sonst leer
        $hasBkv = $employee->benefits->contains(function($benefit) {
            return $benefit->benefit_type === 'bkv' && $benefit->is_active;
        });
        $row[31] = $hasBkv ? '1' : '';
        
        // 33. KK-Code (Einzugsstelle) (Index 32)
        $row[32] = $employee->healthInsuranceCompany?->ik_number ?? '';
        
        // 42. Kinder (Index 41)
        $row[41] = (string)($employee->children_count ?? 0);
        
        // 43. Kinder unter 25 Jahren für PV-Abschlag (Index 42)
        // TODO: Später aus Daten
        $row[42] = '';
        
        // 44. PV-Beitrag privat (Index 43)
        // TODO: Später aus Daten
        $row[43] = '';
        
        // 45-47. Umlagepflicht (U1, U2, Insolvenz) - Indizes 44-46
        // Immer "Ja" setzen
        $row[44] = 'Ja'; // Umlagepflicht U1 Lfz
        $row[45] = 'Ja'; // Umlagepflicht U2
        $row[46] = 'Ja'; // Umlagepflicht Insolvenz
        
        // 48. Staatsangehörigkeitsschlüssel (Index 47)
        // Aus importierten Daten, normalisiert: Immer auf 3 Stellen auffüllen mit führenden Nullen
        $nationality = $employee->nationality ?: '';
        if ($nationality === '' || $nationality === '0') {
            $row[47] = '000';
        } else {
            // Auf 3 Stellen mit führenden Nullen auffüllen
            $row[47] = str_pad((string)$nationality, 3, '0', STR_PAD_LEFT);
        }
        
        // 49. Leere Spalte (Index 48)
        $row[48] = '';
        
        // 50. Rentenbeginn (Index 49)
        // (leer - wird später befüllt wenn vorhanden)
        
        // 52. Beschäftigungsverhältnis (Index 51)
        $row[51] = $contract?->employmentRelationship?->code ?? '';
        
        // 53. Mehrfachbeschäftigt (Index 52)
        $row[52] = $contract?->has_additional_employment ? 'Ja' : 'Nein';
        
        // 54. Rentenart (Index 53)
        $row[53] = $contract?->pensionType?->code ?? '';
        
        // 56. Saisonarbeitnehmer (Index 55)
        $row[55] = $employee->is_seasonal_worker ? 'Ja' : 'Nein';
        
        // 57. Soz.-Versicherungsnr. (Index 56)
        $row[56] = $contract?->social_security_number ?? '';
        
        // 58. Geschlecht (Index 57)
        $gender = $employee->gender ?? $contact?->gender ?? null;
        if ($gender === 'male' || $gender === 'männlich' || $gender === 'Männlich') {
            $row[57] = 'Männlich';
        } elseif ($gender === 'female' || $gender === 'weiblich' || $gender === 'Weiblich') {
            $row[57] = 'Weiblich';
        }
        
        // 59. Geburtsdatum (Index 58)
        $birthDate = $employee->birth_date ?? $contact?->birth_date;
        if ($birthDate) {
            $row[58] = is_string($birthDate) ? date('d.m.Y', strtotime($birthDate)) : $birthDate->format('d.m.Y');
        }
        
        // 60. Geburtsname (Index 59)
        $row[59] = $employee->birth_surname ?? $contact?->last_name ?? '';
        
        // 61. Geburtsort (Index 60)
        $row[60] = $employee->birth_place ?? $contact?->birth_place ?? '';
        
        // 64. Geburtsland (Index 63)
        $row[63] = $employee->birth_country ?? '';
        
        // 65. Krankenkasse (tats.) (Index 64)
        $row[64] = $employee->healthInsuranceCompany?->name ?? '';
        
        // 66. PGR 109/110:Versicherungsstatus (Index 65)
        $row[65] = $contract?->insuranceStatus?->code ?? '';
        
        // 67. steuerpflichtig EStG § 1 (Index 66)
        $row[66] = 'unbeschränkt';
        
        // 68. Art der Besteuerung (Index 67)
        $row[67] = 'individuell';
        
        // 70. Identifikationsnummer (Index 69)
        $row[69] = $employee->tax_id_number ?? '';
        
        // 74. Steuerklasse (Index 73)
        $row[73] = $contract?->taxClass?->code ?? '';
        
        // 76. Kinderfreibetrag (Index 75)
        $row[75] = (string)($employee->child_allowance ?? 0);
        
        // 81. Konfession (Index 80)
        $row[80] = $employee->church_tax ?? '';
        
        // 90-92. Arbeitszeit
        // Teilzeitfaktor, Entgeltfaktor, Teilzeit Grund (Indizes 89-91)
        $hoursPerWeek = $contract?->hours_per_month ? ($contract->hours_per_month / 4.333) : null;
        if ($hoursPerWeek) {
            $row[89] = number_format($hoursPerWeek, 2, ',', '');
        }
        
        // Teilzeitfaktor berechnen
        if ($hoursPerWeek && $hoursPerWeek < 40) {
            $row[90] = number_format($hoursPerWeek / 40, 2, ',', '');
        } else {
            $row[90] = '1,00';
        }
        
        $row[91] = '1,00'; // Entgeltfaktor
        
        // 93. Funktion (Index 92)
        $jobTitle = $contract?->jobTitles->first();
        $row[92] = $jobTitle?->name ?? '';
        
        // 94. Beschäftigung in (Index 93)
        $row[93] = 'Firma';
        
        // 95. Tätigkeitsschlüssel (9-stellig) (Index 94)
        // Stellen 1-5: Tätigkeitscode
        // Stelle 6: Schulabschluss (schooling_level)
        // Stelle 7: Berufsausbildung (vocational_training_level)
        // Stelle 8: Leiharbeit (1=nein, 2=ja)
        // Stelle 9: Vertragsform (contract_form)
        $activityCode = str_pad($contract?->primaryJobActivity?->code ?? '00000', 5, '0', STR_PAD_LEFT);
        $schooling = (string)($contract?->schooling_level ?? 0);
        $vocational = (string)($contract?->vocational_training_level ?? 0);
        $tempAgency = $contract?->is_temp_agency ? '2' : '1';
        $contractForm = (string)($contract?->contract_form ?? 0);
        $row[94] = $activityCode . $schooling . $vocational . $tempAgency . $contractForm;
        
        // 99. Entgelt Art (Index 98)
        $wageType = $contract?->wage_base_type ?? '';
        if ($wageType === 'hourly' || $contract?->hourly_wage) {
            $row[98] = 'Stundenlohn';
        } elseif ($wageType === 'monthly' || $contract?->base_salary) {
            $row[98] = 'Monatslohn/Gehalt';
        }
        
        // 100-104. Tarif
        // Tarifart (Index 99)
        $tariffAgreement = $contract?->tariffGroup?->tariffAgreement;
        if ($tariffAgreement) {
            $row[99] = $tariffAgreement->name ?? '';
        }
        // Tarifgruppe (Index 100)
        $row[100] = $contract?->tariffGroup?->code ?? '';
        // Tarifbasisdatum (Index 101)
        $row[101] = $contract?->tariff_assignment_date?->format('d.m.Y') ?? '';
        // Tarifstufe (Index 102)
        $row[102] = $contract?->tariffLevel?->level ?? '';
        // Tarifstufe seit (Index 103)
        $row[103] = $contract?->tariff_level_start_date?->format('d.m.Y') ?? '';
        
        // 109. Urlaubsverwaltung (Index 108)
        if ($contract?->vacation_entitlement) {
            $row[108] = 'Jahresanspruch';
        }
        
        // 112-113. Dienststelle & Ort Dienststelle (Indizes 111-112)
        if ($costCenter) {
            $row[111] = is_object($costCenter) ? ($costCenter->name ?? '') : '';
            $row[112] = is_object($costCenter) ? ($costCenter->name ?? '') : '';
        }
        
        // 115. Familienstand (Index 114)
        $row[114] = $contact?->marital_status ?? '';
        
        // 145-147. Kommunikation (privat) (Indizes 144-146)
        $row[144] = $primaryPhone?->number ?? '';
        $row[145] = $contact?->phoneNumbers->where('type', 'mobile')->first()?->number ?? '';
        $row[146] = $primaryEmail?->email ?? '';
        
        // Alle anderen Felder bleiben leer (werden später befüllt wenn Daten vorhanden)
        
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
        // Exports in geschütztem Storage-Verzeichnis speichern (nicht public)
        Storage::disk('local')->put($filepath, $csvData);

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
            $field = (string) $field;
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
        $content = Storage::disk('local')->get($filepath);
        $lines = explode("\n", $content);
        return max(0, count($lines) - 6); // Minus Header-Zeilen (5) und Trailer (1)
    }
}
