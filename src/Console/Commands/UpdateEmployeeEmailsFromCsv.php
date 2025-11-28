<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Crm\Models\CrmEmailAddress;
use Platform\Crm\Models\CrmEmailType;
use Illuminate\Support\Facades\Log;

class UpdateEmployeeEmailsFromCsv extends Command
{
    protected $signature = 'hcm:update-emails-from-csv {csv_path} {--dry-run : Show what would be updated without actually updating}';
    protected $description = 'Update employee email addresses from CSV correction file';

    protected int $processedCount = 0;
    protected int $createdCount = 0;
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

        // Hole Standard E-Mail-Typ (Geschäftlich/BUSINESS)
        $emailType = CrmEmailType::where('code', 'BUSINESS')
            ->orWhere('name', 'Geschäftlich')
            ->orWhere('id', 1) // Fallback auf ID 1
            ->first();

        if (!$emailType) {
            $this->error('E-Mail-Typ nicht gefunden. Bitte sicherstellen, dass crm_email_types Tabelle befüllt ist.');
            return 1;
        }

        if ($dryRun) {
            $this->info("DRY RUN MODE - No data will be updated");
        }

        $this->info("Starting email update from CSV: {$csvPath}");
        $this->info("Employer ID: {$employerId}");
        $this->info("Email Type: {$emailType->name} (ID: {$emailType->id})");
        $this->newLine();

        // CSV lesen
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->error("Could not open CSV file: {$csvPath}");
            return 1;
        }

        // Erste Zeile überspringen (Header, falls vorhanden)
        $firstLine = fgetcsv($handle, 0, ';');
        
        // Prüfe ob erste Zeile ein Header ist (enthält "employee_number" oder ähnlich)
        if ($firstLine && (stripos($firstLine[0] ?? '', 'employee') !== false || stripos($firstLine[0] ?? '', 'nummer') !== false)) {
            $this->info("Skipping header row");
        } else {
            // Zurück zum Anfang
            rewind($handle);
        }

        // Zeilen verarbeiten
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (empty($row[0])) {
                continue; // Leere Zeilen überspringen
            }

            $this->processRow($row, $employerId, $emailType->id, $dryRun);
        }

        fclose($handle);

        // Zusammenfassung
        $this->newLine();
        $this->info("=== Zusammenfassung ===");
        $this->info("Verarbeitet: {$this->processedCount}");
        $this->info("Erstellt: {$this->createdCount}");
        $this->info("Aktualisiert: {$this->updatedCount}");
        $this->info("Übersprungen (gleich): {$this->skippedCount}");
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

    protected function processRow(array $row, int $employerId, int $emailTypeId, bool $dryRun): void
    {
        try {
            // CSV Format: employee_number; salutation; first_name; last_name; street; email; ...
            $employeeNumber = trim($row[0] ?? '');
            $emailFromCsv = trim($row[5] ?? ''); // Spalte 6 (Index 5)

            if (empty($employeeNumber)) {
                return; // Keine Employee Number, überspringen
            }

            if (empty($emailFromCsv)) {
                $this->warn("  Zeile ohne E-Mail übersprungen: Employee {$employeeNumber}");
                return;
            }

            // Validiere E-Mail-Format
            if (!filter_var($emailFromCsv, FILTER_VALIDATE_EMAIL)) {
                $this->warn("  Ungültige E-Mail-Adresse übersprungen: {$emailFromCsv} (Employee {$employeeNumber})");
                return;
            }

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

            // Hole Contact
            $contact = $employee->getContact();
            if (!$contact) {
                $this->warn("  Kein CRM-Contact für Employee {$employeeNumber} gefunden");
                $this->errors[] = "Kein CRM-Contact für Employee {$employeeNumber}";
                $this->errorCount++;
                return;
            }

            // Hole aktive E-Mail-Adressen
            $existingEmails = $contact->emailAddresses()
                ->active()
                ->get();

            // Prüfe ob bereits eine E-Mail vorhanden ist (egal welche)
            $anyExistingEmail = $existingEmails->first();
            
            // Prüfe ob die E-Mail aus CSV bereits existiert (case-insensitive)
            $matchingEmail = $existingEmails->first(function ($email) use ($emailFromCsv) {
                return strtolower(trim($email->email_address)) === strtolower(trim($emailFromCsv));
            });

            if (!$anyExistingEmail) {
                // Keine E-Mail vorhanden → Neue erstellen
                if (!$dryRun) {
                    $contact->emailAddresses()->create([
                        'email_address' => $emailFromCsv,
                        'email_type_id' => $emailTypeId,
                        'is_primary' => true,
                        'is_active' => true,
                    ]);
                }
                $this->info("  ✓ Employee {$employeeNumber}: E-Mail erstellt: {$emailFromCsv}");
                $this->createdCount++;
            } elseif ($matchingEmail) {
                // E-Mail aus CSV existiert bereits → Als primary setzen (falls nicht schon)
                if (!$matchingEmail->is_primary) {
                    if (!$dryRun) {
                        // Alle anderen E-Mails auf nicht primär setzen
                        $contact->emailAddresses()
                            ->where('id', '!=', $matchingEmail->id)
                            ->update(['is_primary' => false]);
                        
                        // Diese E-Mail als primär setzen
                        $matchingEmail->update(['is_primary' => true]);
                    }
                    $this->info("  ✓ Employee {$employeeNumber}: E-Mail als primär gesetzt: {$emailFromCsv}");
                    $this->updatedCount++;
                } else {
                    // E-Mail ist bereits vorhanden und primär → Überspringen
                    $this->line("  - Employee {$employeeNumber}: E-Mail bereits korrekt und primär: {$emailFromCsv}");
                    $this->skippedCount++;
                }
            } else {
                // E-Mail vorhanden, aber unterschiedlich → Ersetzen (nicht zusätzlich)
                $oldEmail = $anyExistingEmail->email_address;
                if (!$dryRun) {
                    // Alle anderen E-Mails auf nicht primär setzen
                    $contact->emailAddresses()
                        ->where('id', '!=', $anyExistingEmail->id)
                        ->update(['is_primary' => false]);
                    
                    // Bestehende E-Mail ersetzen
                    $anyExistingEmail->update([
                        'email_address' => $emailFromCsv,
                        'is_primary' => true,
                    ]);
                }
                $this->info("  ✓ Employee {$employeeNumber}: E-Mail ersetzt: {$oldEmail} → {$emailFromCsv}");
                $this->updatedCount++;
            }

            $this->processedCount++;

        } catch (\Exception $e) {
            $employeeNumber = $row[0] ?? 'unknown';
            $this->error("  ✗ Fehler bei Employee {$employeeNumber}: {$e->getMessage()}");
            $this->errors[] = "Employee {$employeeNumber}: {$e->getMessage()}";
            $this->errorCount++;
            Log::error("Error processing employee email from CSV", [
                'employee_number' => $employeeNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

