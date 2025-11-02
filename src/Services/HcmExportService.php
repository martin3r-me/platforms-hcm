<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmExport;
use Platform\Hcm\Models\HcmExportTemplate;
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
            $export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
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

        // Lade Mitarbeiter mit allen benötigten Relationen
        $employees = \Platform\Hcm\Models\HcmEmployee::with([
            'employer',
            'contracts' => function($q) {
                $q->where('is_active', true)
                  ->orderBy('start_date', 'desc');
            },
            'contracts.tariffGroup',
            'contracts.tariffLevel',
            'contracts.insuranceStatus',
            'contracts.pensionType',
            'contracts.employmentRelationship',
            'contracts.personGroup',
            'contracts.primaryJobActivity',
            'contracts.jobActivityAlias',
            'healthInsuranceCompany',
            'payoutMethod',
        ])
        ->where('team_id', $this->teamId)
        ->where('is_active', true)
        ->get();

        $csvData = $this->generateInfoniqaCsv($employees);

        // UTF-8 BOM für Excel-Kompatibilität
        $csvData = "\xEF\xBB\xBF" . $csvData;
        
        Storage::disk('public')->put($filepath, $csvData);

        return $filepath;
    }

    /**
     * Generiert INFONIQA-CSV Format (mehrere Header-Zeilen)
     */
    private function generateInfoniqaCsv(Collection $employees): string
    {
        $lines = [];
        
        // Zeile 1: Mandantennummer + Kategorie
        $lines[] = $this->teamId . ';Konv Mitarbeiter' . str_repeat(';', 269);
        
        // Zeile 2: Kategorien (vereinfacht - würde normalerweise aus Template kommen)
        $categoryLine = str_repeat('Mitarbeiter Allgemein;', 27) . 
                        str_repeat('Mitarbeiter SV1;', 20) . 
                        str_repeat('Mitarbeiter SV2;', 10) . 
                        str_repeat('Mitarbeiter Steuer;', 23) . 
                        str_repeat('Mitarbeiter Verwaltung;', 13) . 
                        str_repeat('Mitarbeiter Tarif/ZVK;', 10) . 
                        str_repeat('Mitarbeiter Sonstiges;', 19) . 
                        str_repeat('Mitarbeiter Kommunikation;', 21) . 
                        ';' . 
                        str_repeat('Mitarbeiter Betriebsrentner;', 7) . 
                        'Anfangswerte;;' . 
                        str_repeat('Mitarbeiter SV1 - WfbM;', 3) . ';' . 
                        str_repeat('Mitarbeiter ATZ/FLEX;', 10) . ';' . 
                        str_repeat('Mitarbeiter ATZ/FLEX;', 5) . ';;' . 
                        str_repeat('Mitarbeiter Steuer - KuG;', 3) . ';;' . 
                        str_repeat('Mitarbeiter Tarif/ZVK - Zusatzversorgung;', 3) . ';;' . 
                        str_repeat('Mitarbeiter Tarif/ZVK - Baulohn;', 3) . ';;' . 
                        'Berufsständisch Versicherte AN;Berufsständisch Versicherte AN;;' . 
                        'Steuer - Aufwandspauschale;Verwaltung;spezielle Felder;spezielle Felder;spezielle Felder;';
        $lines[] = $categoryLine;
        
        // Zeile 3: Feldtypen (vereinfacht)
        // Zeile 4: Datentypen (vereinfacht)
        // Zeile 5: Spaltenüberschriften
        $headers = $this->getInfoniqaHeaders();
        $lines[] = implode(';', array_fill(0, 270, '1')); // Platzhalter für Zeile 3
        $lines[] = implode(';', array_fill(0, 270, 'Code10')); // Platzhalter für Zeile 4
        $lines[] = implode(';', $headers);
        
        // Datenzeilen
        foreach ($employees as $employee) {
            $contract = $employee->contracts->first();
            $lines[] = $this->generateInfoniqaEmployeeRow($employee, $contract);
        }
        
        // Leere Zeile + Trailer
        $lines[] = str_repeat(';', 16) . '0000' . str_repeat(';', 253);
        
        return implode("\n", $lines);
    }

    /**
     * Gibt die INFONIQA-Header zurück
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
            // ... weitere Header (vereinfacht, würde normalerweise aus Template kommen)
            // Hier sollten alle 270 Spalten definiert werden
        ];
    }

    /**
     * Generiert eine Mitarbeiter-Zeile im INFONIQA-Format
     */
    private function generateInfoniqaEmployeeRow($employee, $contract): string
    {
        $contact = $employee->crmContactLinks->first()?->contact;
        
        $row = [
            $employee->employee_number ?? '',
            $contact?->title ?? '',
            '', // Titel
            '', // Namenszusatz
            $contact?->first_name ?? '',
            '', // Namensvorsatz
            $contact?->last_name ?? '',
            $contact?->addresses->first()?->street ?? '',
            $contact?->addresses->first()?->house_number ?? '',
            '', // Hausnr.-zusatz
            '', // Adresszusatz
            $contact?->addresses->first()?->country ?? 'DE',
            $contact?->addresses->first()?->postal_code ?? '',
            $contact?->addresses->first()?->city ?? '',
            $employee->employer?->employer_number ?? '',
            $contract?->cost_center ?? '',
            $contract?->start_date?->format('d.m.Y') ?? '',
            $contract?->end_date?->format('d.m.Y') ?? '',
            '', // Austrittsgrund
            $contract?->end_date?->format('d.m.Y') ?? '',
            '', // Befristungsgrund
            $contract?->probation_end_date?->format('d.m.Y') ?? '',
            '', // Entlassung
            '', // Freistellung
            $contract?->start_date?->format('d.m.Y') ?? '',
            '', // Berufserfahrung seit
            '', // Ausbildung Ende
            $contract?->personGroup?->code ?? '',
            '', // Beitragsgruppe
            // ... weitere Felder
        ];
        
        // Auf 270 Spalten auffüllen
        while (count($row) < 270) {
            $row[] = '';
        }
        
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
        return max(0, count($lines) - 5); // Minus Header-Zeilen
    }
}

