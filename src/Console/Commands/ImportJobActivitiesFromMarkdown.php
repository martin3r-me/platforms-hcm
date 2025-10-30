<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmJobActivity;
use Platform\Hcm\Models\HcmJobActivityAlias;

class ImportJobActivitiesFromMarkdown extends Command
{
    protected $signature = 'hcm:import-job-activities 
        {--file= : Absoluter Pfad zur Markdown-Datei (Tätigkeitsschlüssel)}
        {--team-id= : Team-ID für die Datensätze}
        {--dry-run : Nur parsen und zählen, keine Inserts}';

    protected $description = 'Importiert Tätigkeitsschlüssel (Code + Bezeichnung) aus einer Markdown-Datei in HcmJobActivity';

    public function handle(): int
    {
        $file = $this->option('file');
        $teamId = (int) ($this->option('team-id') ?? auth()->user()?->current_team_id);
        $dryRun = (bool) $this->option('dry-run');

        if (!$file || !is_readable($file)) {
            $this->error('Datei nicht lesbar. Bitte mit --file=/absoluter/pfad.md angeben.');
            return 1;
        }
        if (!$teamId) {
            $this->error('Team-ID fehlt. Bitte --team-id angeben oder eingeloggter User benötigt current_team_id.');
            return 1;
        }

        $this->info("Import aus: {$file}");

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error('Konnte Datei nicht öffnen.');
            return 1;
        }

        $buffer = '';
        $totalParsed = 0;
        $inserted = 0;
        $skipped = 0;
        $inAlphabeticalList = false; // beginne Parsing erst ab der Tätigkeitsliste

        $uniqueCodes = [];

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            // Entferne Füllpunkte und Tabs
            $clean = preg_replace('/[\.·•]+/u', ' ', $line);
            $clean = preg_replace('/\s+/', ' ', $clean);

            // Starte erst ab der Überschrift der alphabetischen Liste
            if (!$inAlphabeticalList) {
                if (stripos($clean, 'ALPHABETISCHES VERZEICHNIS DER BERUFSBENENNUNGEN') !== false) {
                    $inAlphabeticalList = true;
                }
                continue;
            }

            // Skip offensichtliche Metadaten-/Überschrifts-Zeilen
            if ($clean === '' || preg_match('/^(IMPRESSUM|INHALTSVERZEICHNIS|Schlüsselverzeichnis|[A-ZÄÖÜ]$|Schlüsselverzeichnis für die Angaben zur Tätigkeit)/u', $clean)) {
                continue;
            }

            // Akkumuliere bis eine Zeile mit 5-stelligem Code endet
            $buffer = trim($buffer . ' ' . $clean);

            if (preg_match('/(.+?)\s(\d{5})$/u', $buffer, $m)) {
                $name = trim($m[1]);
                $code = $m[2];

                // Filter: valide Namen, sinnvolle Länge, muss Buchstaben enthalten
                if ($name !== '' && !preg_match('/^\d+$/', $name) && preg_match('/[A-Za-zÄÖÜäöüß]/u', $name) && mb_strlen($name) <= 255) {
                    $totalParsed++;

                    if (!$dryRun) {
                        $activity = HcmJobActivity::where('team_id', $teamId)->where('code', $code)->first();
                        if (!$activity) {
                            $activity = HcmJobActivity::create([
                                'code' => $code,
                                'name' => $name,
                                'is_active' => true,
                                'team_id' => $teamId,
                                'created_by_user_id' => auth()->id(),
                            ]);
                            $inserted++;
                            $uniqueCodes[$code] = true;
                        } else {
                            $skipped++;
                        }

                        // Alias anlegen, wenn Name von Primärname abweicht und noch nicht vorhanden
                        if ($activity && mb_strtolower($name) !== mb_strtolower($activity->name)) {
                            $aliasExists = HcmJobActivityAlias::where('team_id', $teamId)
                                ->where('alias', $name)
                                ->exists();
                            if (!$aliasExists) {
                                HcmJobActivityAlias::create([
                                    'job_activity_id' => $activity->id,
                                    'alias' => $name,
                                    'team_id' => $teamId,
                                    'created_by_user_id' => auth()->id(),
                                ]);
                            }
                        }
                    } else {
                        $uniqueCodes[$code] = true;
                    }
                }

                // Buffer zurücksetzen nach erfolgreichem Match
                $buffer = '';
                continue;
            }

            // Sicherheitsreset, falls Buffer zu lang wird (Vermeidung von Fluten aus Meta-Abschnitten)
            if (mb_strlen($buffer) > 500) {
                $buffer = '';
            }
        }

        fclose($handle);

        $this->info("Gefunden: {$totalParsed}, Eingefügt: {$inserted}, Übersprungen: {$skipped}");
        if ($dryRun) {
            $this->info('Dry-Run: Keine Inserts erfolgt.');
        }

        return 0;
    }
}


