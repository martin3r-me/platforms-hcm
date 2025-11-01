<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployeeBenefit;
use Carbon\Carbon;

class ImportVwlBenefits extends Command
{
    protected $signature = 'hcm:import-vwl {employer_id} {csv_path} {--dry-run}';

    protected $description = 'Importiert VWL-Daten (Vermögenswirksame Leistungen) aus CSV und ordnet sie Mitarbeitern zu';

    public function handle(): int
    {
        $employerId = (int) $this->argument('employer_id');
        $csvPath = $this->argument('csv_path');
        $dryRun = $this->option('dry-run');

        // Employer laden und Team-ID ermitteln
        $employer = \Platform\Hcm\Models\HcmEmployer::find($employerId);
        if (!$employer) {
            $this->error("Arbeitgeber mit ID {$employerId} nicht gefunden");
            return self::FAILURE;
        }

        $teamId = $employer->team_id;

        if (!file_exists($csvPath)) {
            $this->error("CSV-Datei nicht gefunden: {$csvPath}");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("DRY RUN MODE - Keine Daten werden geschrieben");
        }

        $this->info("VWL-Import startet: {$csvPath}");
        $this->info("Arbeitgeber: {$employer->display_name} (Team-ID: {$teamId})");

        $stats = [
            'rows_processed' => 0,
            'benefits_created' => 0,
            'benefits_updated' => 0,
            'employees_not_found' => 0,
            'errors' => [],
        ];

        try {
            // CSV öffnen und parsen
            $handle = fopen($csvPath, 'r');
            if (!$handle) {
                throw new \Exception("Konnte CSV-Datei nicht öffnen: {$csvPath}");
            }

            // Header-Zeile lesen
            $header = fgetcsv($handle, 0, ';');
            if (!$header) {
                throw new \Exception("CSV-Header konnte nicht gelesen werden");
            }

            // Header normalisieren (trim, UTF-8, BOM entfernen)
            $header = array_map(function($h) {
                $h = trim(mb_convert_encoding($h, 'UTF-8', 'Windows-1252'));
                // BOM entfernen (UTF-8 BOM: 0xEF 0xBB 0xBF)
                if (substr($h, 0, 3) === "\xEF\xBB\xBF") {
                    $h = substr($h, 3);
                }
                // Alternative: Entferne unsichtbare BOM-Zeichen
                $h = preg_replace('/^\x{FEFF}/u', '', $h);
                return trim($h);
            }, $header);

            $this->info("Header: " . implode(' | ', $header));

            $rowNum = 1;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $rowNum++;
                
                // Zeile normalisieren
                $row = array_map(function($r) {
                    return trim(mb_convert_encoding($r, 'UTF-8', 'Windows-1252'));
                }, $row);

                // Leere Zeilen überspringen
                if (empty(array_filter($row))) {
                    continue;
                }

                // Daten in assoziatives Array umwandeln
                $data = array_combine($header, $row);
                if (!$data) {
                    $this->warn("Zeile {$rowNum}: Konnte nicht geparst werden (Header: " . implode(', ', array_keys($data ?: [])) . ")");
                    continue;
                }

                // Personalnummer finden (verschiedene Varianten)
                $personalNr = trim($data['Personalnummer'] ?? $data['ï»¿Personalnummer'] ?? $data[array_keys($data)[0] ?? ''] ?? '');
                if (empty($personalNr)) {
                    $this->warn("Zeile {$rowNum}: Keine Personalnummer gefunden (verfügbare Felder: " . implode(', ', array_keys($data)) . ")");
                    continue;
                }

                $stats['rows_processed']++;

                try {
                    // Mitarbeiter finden (über Employer-ID, Team ergibt sich automatisch)
                    $employee = HcmEmployee::where('employer_id', $employerId)
                        ->where('employee_number', $personalNr)
                        ->first();

                    if (!$employee) {
                        $this->warn("  → Personalnummer {$personalNr}: Mitarbeiter nicht gefunden");
                        $stats['employees_not_found']++;
                        continue;
                    }

                    // Aktuellen Vertrag finden (aktiv oder neuester)
                    $contract = $employee->contracts()
                        ->where('is_active', true)
                        ->orderBy('start_date', 'desc')
                        ->first();
                    
                    if (!$contract) {
                        $contract = $employee->contracts()->orderBy('start_date', 'desc')->first();
                    }

                    if (!$contract) {
                        $this->warn("  → Personalnummer {$personalNr}: Kein Vertrag gefunden");
                        $stats['errors'][] = "Personalnummer {$personalNr}: Kein Vertrag gefunden";
                        continue;
                    }

                    // VWL-Daten parsen (verschiedene Varianten des Headernamens prüfen)
                    $gesamtbetragRaw = $data['Gesamtbetrag'] ?? $data['ï»¿Gesamtbetrag'] ?? '';
                    $agAnteilRaw = $data['AG-Anteil'] ?? '';
                    $anbieter = trim($data['Anbieter'] ?? '');
                    $kontonummer = trim($data['Kontonummer'] ?? '');
                    $vertragsnummer = trim($data['Vertragsnummer'] ?? '');
                    
                    $gesamtbetrag = $this->parseEuro($gesamtbetragRaw);
                    $agAnteil = $this->parseEuro($agAnteilRaw);

                    if ($gesamtbetrag === null || $gesamtbetrag <= 0) {
                        $this->warn("  → Personalnummer {$personalNr}: Ungültiger Gesamtbetrag (Rohwert: '{$gesamtbetragRaw}', verfügbare Felder: " . implode(', ', array_keys($data)) . ")");
                        continue;
                    }

                    // AN-Anteil = Gesamtbetrag - AG-Anteil
                    $anAnteil = $gesamtbetrag - ($agAnteil ?? 0);

                    $this->line("  → Personalnummer {$personalNr}: {$employee->employee_number} - Gesamt: {$gesamtbetrag}€, AG: {$agAnteil}€, AN: {$anAnteil}€");

                    if (!$dryRun) {
                        // Prüfe ob VWL-Benefit bereits existiert
                        $existingBenefit = HcmEmployeeBenefit::where('employee_id', $employee->id)
                            ->where('employee_contract_id', $contract->id)
                            ->where('benefit_type', 'vwl')
                            ->where(function($q) use ($vertragsnummer, $kontonummer) {
                                if ($vertragsnummer) {
                                    $q->where('contract_number', $vertragsnummer);
                                }
                                if ($kontonummer) {
                                    $q->orWhereJsonContains('benefit_specific_data->account_number', $kontonummer);
                                }
                            })
                            ->first();

                        if ($existingBenefit) {
                            // Update bestehendes Benefit
                            $existingBenefit->update([
                                'monthly_contribution_employee' => (string) $anAnteil,
                                'monthly_contribution_employer' => (string) ($agAnteil ?? 0),
                                'insurance_company' => $anbieter ?: null,
                                'contract_number' => $vertragsnummer ?: null,
                                'benefit_specific_data' => array_filter([
                                    'account_number' => $kontonummer ?: null,
                                ]),
                                'is_active' => true,
                            ]);
                            $stats['benefits_updated']++;
                            $this->info("    ✓ VWL-Benefit aktualisiert");
                        } else {
                            // Neues Benefit erstellen
                            HcmEmployeeBenefit::create([
                                'team_id' => $teamId,
                                'employee_id' => $employee->id,
                                'employee_contract_id' => $contract->id,
                                'benefit_type' => 'vwl',
                                'name' => 'Vermögenswirksame Leistungen',
                                'insurance_company' => $anbieter ?: null,
                                'contract_number' => $vertragsnummer ?: null,
                                'monthly_contribution_employee' => (string) $anAnteil, // Verschlüsselt
                                'monthly_contribution_employer' => (string) ($agAnteil ?? 0), // Verschlüsselt
                                'contribution_frequency' => 'monthly',
                                'benefit_specific_data' => array_filter([
                                    'account_number' => $kontonummer ?: null,
                                ]),
                                'start_date' => $contract->start_date?->toDateString(),
                                'is_active' => true,
                                'created_by_user_id' => $employer->created_by_user_id,
                            ]);
                            $stats['benefits_created']++;
                            $this->info("    ✓ VWL-Benefit erstellt");
                        }
                    } else {
                        $this->info("    [DRY RUN] VWL-Benefit würde erstellt/aktualisiert");
                        $stats['benefits_created']++;
                    }

                } catch (\Throwable $e) {
                    $errorMsg = "Zeile {$rowNum} (Personalnummer {$personalNr}): {$e->getMessage()}";
                    $this->error("  ✗ " . $errorMsg);
                    $stats['errors'][] = $errorMsg;
                }
            }

            fclose($handle);

        } catch (\Throwable $e) {
            $this->error("FEHLER beim Import: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Statistiken anzeigen
        $this->newLine();
        $this->info("=== Import abgeschlossen ===");
        $this->table(['Metrik', 'Wert'], [
            ['Zeilen verarbeitet', $stats['rows_processed']],
            ['VWL-Benefits erstellt', $stats['benefits_created']],
            ['VWL-Benefits aktualisiert', $stats['benefits_updated']],
            ['Mitarbeiter nicht gefunden', $stats['employees_not_found']],
            ['Fehler', count($stats['errors'])],
        ]);

        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->error("Fehler:");
            foreach ($stats['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Parst einen Euro-Betrag aus String (z.B. " 40,00 € " -> 40.0)
     */
    private function parseEuro(?string $value): ?float
    {
        if (!$value || trim($value) === '') {
            return null;
        }

        // Trim und normalisiere
        $cleaned = trim($value);
        
        // Entferne Euro-Symbol (Unicode-Variante)
        $cleaned = preg_replace('/[€\x{20AC}]/u', '', $cleaned);
        
        // Entferne alle Leerzeichen
        $cleaned = preg_replace('/\s+/', '', $cleaned);
        
        // Entferne Tausender-Trennzeichen (Punkte)
        $cleaned = str_replace('.', '', $cleaned);
        
        // Ersetze Komma durch Punkt (Deutsches Format: 40,00 -> 40.00)
        $cleaned = str_replace(',', '.', $cleaned);
        
        $float = filter_var($cleaned, FILTER_VALIDATE_FLOAT);
        
        return $float !== false ? $float : null;
    }
}

