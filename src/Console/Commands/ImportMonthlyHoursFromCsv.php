<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Models\HcmEmployer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImportMonthlyHoursFromCsv extends Command
{
    protected $signature = 'hcm:import-monthly-hours 
                            {csv_path : Pfad zur CSV-Datei}
                            {employer_id : ID des Arbeitgebers}
                            {--dry-run : Zeige was aktualisiert würde, ohne tatsächlich zu aktualisieren}';

    protected $description = 'Import Monatsstunden aus CSV (Format: Personalnummer;Monatsstunden) und berechne Wochenstunden automatisch';

    protected int $processedCount = 0;
    protected int $updatedCount = 0;
    protected int $skippedCount = 0;
    protected int $errorCount = 0;
    protected int $notFoundCount = 0;
    protected array $errors = [];

    public function handle()
    {
        $csvPath = $this->argument('csv_path');
        $employerId = (int)$this->argument('employer_id');
        $dryRun = $this->option('dry-run');

        // Validierung
        if (!file_exists($csvPath)) {
            $this->error("CSV-Datei nicht gefunden: {$csvPath}");
            return 1;
        }

        // Employer prüfen
        $employer = HcmEmployer::find($employerId);
        if (!$employer) {
            $this->error("Arbeitgeber mit ID {$employerId} nicht gefunden");
            return 1;
        }

        if ($dryRun) {
            $this->info("DRY RUN MODUS - Keine Daten werden aktualisiert");
        }

        $this->info("Starte Import von Monatsstunden aus CSV: {$csvPath}");
        $this->info("Arbeitgeber: {$employer->display_name} (ID: {$employerId})");
        $this->newLine();

        // CSV lesen
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->error("CSV-Datei konnte nicht geöffnet werden: {$csvPath}");
            return 1;
        }

        // Zeilen verarbeiten (kein Header in dieser CSV)
        $lineNumber = 0;
        
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

            // Progress-Anzeige alle 20 Zeilen
            if ($lineNumber % 20 === 0) {
                $this->line("  ... {$lineNumber} Zeilen verarbeitet ...");
            }

            $this->processRow($row, $employerId, $dryRun);
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
        $this->info("Nicht gefunden: {$this->notFoundCount}");
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
            $this->warn("DRY RUN MODUS - Keine Daten wurden tatsächlich aktualisiert!");
        }

        return 0;
    }

    protected function processRow(array $row, int $employerId, bool $dryRun): void
    {
        try {
            // CSV Format: Personalnummer;Monatsstunden
            // Index:      0               1
            $employeeNumber = trim($row[0] ?? '');
            $monthlyHoursStr = trim($row[1] ?? '');

            if (empty($employeeNumber)) {
                return; // Keine Employee Number, überspringen
            }

            if (empty($monthlyHoursStr) || $monthlyHoursStr === '') {
                $this->warn("  Zeile ohne Monatsstunden übersprungen: Employee {$employeeNumber}");
                return;
            }

            // Konvertiere Komma zu Punkt für Dezimalzahl
            $monthlyHours = (float) str_replace(',', '.', $monthlyHoursStr);

            if ($monthlyHours < 0) {
                $this->warn("  Ungültige Monatsstunden übersprungen: {$monthlyHoursStr} (Employee {$employeeNumber})");
                return;
            }

            // Finde Employee
            $employee = HcmEmployee::where('employee_number', $employeeNumber)
                ->where('employer_id', $employerId)
                ->first();

            if (!$employee) {
                $this->warn("  Employee nicht gefunden: {$employeeNumber} (Employer ID: {$employerId})");
                $this->errors[] = "Employee {$employeeNumber} nicht gefunden";
                $this->notFoundCount++;
                return;
            }

            // Finde aktiven Contract
            $today = now()->toDateString();
            $contract = HcmEmployeeContract::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where(function ($q) use ($today) {
                    $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
                })
                ->orderByDesc('start_date')
                ->first();

            if (!$contract) {
                $this->warn("  Kein aktiver Contract für Employee {$employeeNumber} gefunden");
                $this->errors[] = "Kein aktiver Contract für Employee {$employeeNumber}";
                $this->notFoundCount++;
                return;
            }

            // Hole Faktor (aus Contract oder Default 4.4)
            $factor = $contract->hours_per_week_factor ?? 4.4;
            
            // Berechne Wochenstunden: Monatsstunden / Faktor
            $weeklyHours = $monthlyHours > 0 && $factor > 0 
                ? round($monthlyHours / $factor, 2) 
                : null;

            // Prüfe ob Werte sich geändert haben
            $currentMonthlyHours = $contract->hours_per_month;
            $currentWeeklyHours = $contract->hours_per_week;

            if ($currentMonthlyHours == $monthlyHours && $currentWeeklyHours == $weeklyHours) {
                $this->line("  - Employee {$employeeNumber}: Werte bereits korrekt (Monat: {$monthlyHours}h, Woche: {$weeklyHours}h)");
                $this->skippedCount++;
            } else {
                $oldMonthly = $currentMonthlyHours ?? 'nicht gesetzt';
                $oldWeekly = $currentWeeklyHours ?? 'nicht gesetzt';

                if (!$dryRun) {
                    // Update Contract mit Monatsstunden, Faktor und berechneten Wochenstunden
                    DB::table('hcm_employee_contracts')
                        ->where('id', $contract->id)
                        ->update([
                            'hours_per_month' => $monthlyHours,
                            'hours_per_week_factor' => $factor, // Stelle sicher, dass Faktor gesetzt ist
                            'hours_per_week' => $weeklyHours,
                            'updated_at' => now(),
                        ]);
                }

                $this->info("  ✓ Employee {$employeeNumber}: Contract aktualisiert");
                $this->info("     Monatsstunden: {$oldMonthly} → {$monthlyHours}");
                $this->info("     Wochenstunden: {$oldWeekly} → {$weeklyHours} (berechnet: {$monthlyHours} / {$factor})");
                $this->updatedCount++;
            }

            // Memory-Management: Objekte freigeben
            unset($contract, $employee);

            $this->processedCount++;

        } catch (\Exception $e) {
            $employeeNumber = $row[0] ?? 'unknown';
            $this->error("  ✗ Fehler bei Employee {$employeeNumber}: {$e->getMessage()}");
            $this->errors[] = "Employee {$employeeNumber}: {$e->getMessage()}";
            $this->errorCount++;
            Log::error("Error processing monthly hours from CSV", [
                'employee_number' => $employeeNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
