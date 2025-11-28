<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeContract;
use Illuminate\Support\Facades\Log;

class ImportSollstundenFromCsv extends Command
{
    protected $signature = 'hcm:import-sollstunden {csv_path} {--dry-run : Show what would be updated without actually updating}';
    protected $description = 'Import Sollstunden (monthly hours) from CSV and calculate weekly hours';

    protected int $processedCount = 0;
    protected int $updatedCount = 0;
    protected int $skippedCount = 0;
    protected int $errorCount = 0;
    protected array $errors = [];

    public function handle()
    {
        $csvPath = $this->argument('csv_path');
        $dryRun = $this->option('dry-run');

        // Validierung
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        // Employer ID 2
        $employerId = 2;

        if ($dryRun) {
            $this->info("DRY RUN MODE - No data will be updated");
        }

        $this->info("Starting Sollstunden import from CSV: {$csvPath}");
        $this->info("Employer ID: {$employerId}");
        $this->newLine();

        // CSV lesen
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->error("Could not open CSV file: {$csvPath}");
            return 1;
        }

        // Erste Zeile ist Header - überspringen
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            $this->error("Could not read CSV header");
            fclose($handle);
            return 1;
        }

        // BOM-Zeichen entfernen (falls vorhanden)
        if (!empty($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // UTF-8 BOM entfernen
        }

        $this->info("CSV Header: " . implode(' | ', $header));
        $this->newLine();

        // Zeilen verarbeiten
        $lineNumber = 1; // Start bei 1, da Header bereits gelesen
        $processedInBatch = 0;
        
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;
            
            // BOM-Zeichen entfernen (falls vorhanden)
            if (!empty($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }

            // Prüfe ob Zeile leer ist
            if (empty($row) || (count($row) === 1 && empty(trim($row[0])))) {
                continue; // Leere Zeilen überspringen
            }

            // Debug: Zeige erste paar Zeilen
            if ($lineNumber <= 3) {
                $this->line("  [Debug] Zeile {$lineNumber}: " . implode(' | ', array_slice($row, 0, 3)) . '...');
            }

            $this->processRow($row, $employerId, $dryRun);
            $processedInBatch++;

            // Progress-Anzeige alle 10 Zeilen
            if ($processedInBatch % 10 === 0) {
                $this->line("  ... {$processedInBatch} Zeilen verarbeitet ...");
            }
        }

        fclose($handle);
        
        $this->newLine();
        $this->info("CSV-Datei vollständig gelesen ({$lineNumber} Zeilen insgesamt)");

        // Zusammenfassung
        $this->newLine();
        $this->info("=== Zusammenfassung ===");
        $this->info("Verarbeitet: {$this->processedCount}");
        $this->info("Aktualisiert: {$this->updatedCount}");
        $this->info("Übersprungen: {$this->skippedCount}");
        $this->info("Fehler: {$this->errorCount}");

        if (!empty($this->errors)) {
            $this->newLine();
            $this->error("Fehler-Details:");
            foreach ($this->errors as $error) {
                $this->error("  - {$error}");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn("DRY RUN MODE - No data was actually updated!");
        }

        return 0;
    }

    protected function processRow(array $row, int $employerId, bool $dryRun): void
    {
        try {
            // CSV Format: Pers.-Nr;Anzeigename;Nachname;Vorname;Kostenstelle;Monat;Sollstunden;
            // Index:      0           1           2        3        4           5      6
            $employeeNumber = trim($row[0] ?? '');
            $month = trim($row[5] ?? ''); // Format: MM.YYYY
            $sollstunden = trim($row[6] ?? ''); // Format: 176,00 (mit Komma)

            if (empty($employeeNumber)) {
                return; // Keine Employee Number, überspringen
            }

            // Prüfe ob Zeile nur aus leeren Feldern besteht
            if (count(array_filter($row, function($val) { return !empty(trim($val)); })) === 0) {
                return; // Leere Zeile
            }

            if (empty($sollstunden) || $sollstunden === '') {
                $this->warn("  Zeile ohne Sollstunden übersprungen: Employee {$employeeNumber}");
                return;
            }

            // Konvertiere Komma zu Punkt für Dezimalzahl
            $monthlyHours = (float) str_replace(',', '.', $sollstunden);

            if ($monthlyHours <= 0) {
                $this->warn("  Ungültige Sollstunden übersprungen: {$sollstunden} (Employee {$employeeNumber})");
                return;
            }

            // Berechne Wochenstunden: Monatsstunden / 4.4
            $weeklyHours = round($monthlyHours / 4.4, 2);

            // Finde Employee
            $employee = HcmEmployee::where('employee_number', $employeeNumber)
                ->where('employer_id', $employerId)
                ->first();

            if (!$employee) {
                $this->warn("  Employee nicht gefunden: {$employeeNumber} (Employer ID: {$employerId})");
                $this->errors[] = "Employee {$employeeNumber} nicht gefunden";
                $this->errorCount++;
                return;
            }

            // Finde aktiven Contract
            $contract = $employee->activeContract();

            if (!$contract) {
                $this->warn("  Kein aktiver Contract für Employee {$employeeNumber} gefunden");
                $this->errors[] = "Kein aktiver Contract für Employee {$employeeNumber}";
                $this->errorCount++;
                return;
            }

            // Prüfe ob Werte sich geändert haben
            $currentMonthlyHours = $contract->hours_per_month;
            $currentWeeklyHours = $contract->hours_per_week;

            if ($currentMonthlyHours == $monthlyHours && $currentWeeklyHours == $weeklyHours) {
                $this->line("  - Employee {$employeeNumber}: Werte bereits korrekt (Monat: {$monthlyHours}h, Woche: {$weeklyHours}h)");
                $this->skippedCount++;
            } else {
                if (!$dryRun) {
                    $contract->update([
                        'hours_per_month' => $monthlyHours,
                        'hours_per_week' => $weeklyHours,
                    ]);
                }

                $oldMonthly = $currentMonthlyHours ?? 'nicht gesetzt';
                $oldWeekly = $currentWeeklyHours ?? 'nicht gesetzt';

                $this->info("  ✓ Employee {$employeeNumber}: Contract aktualisiert");
                $this->info("     Monatsstunden: {$oldMonthly} → {$monthlyHours}");
                $this->info("     Wochenstunden: {$oldWeekly} → {$weeklyHours} (berechnet: {$monthlyHours} / 4.4)");
                $this->updatedCount++;
            }

            $this->processedCount++;

        } catch (\Exception $e) {
            $employeeNumber = $row[0] ?? 'unknown';
            $this->error("  ✗ Fehler bei Employee {$employeeNumber}: {$e->getMessage()}");
            $this->errors[] = "Employee {$employeeNumber}: {$e->getMessage()}";
            $this->errorCount++;
            Log::error("Error processing sollstunden from CSV", [
                'employee_number' => $employeeNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

