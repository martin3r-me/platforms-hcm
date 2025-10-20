<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\PayrollTypeExportService;

class ExportPayrollTypes extends Command
{
    protected $signature = 'hcm:export-payroll-types 
                            {--team-id= : Team ID für den Export}
                            {--format=csv : Export-Format (csv oder pdf)}
                            {--output= : Ausgabepfad (optional)}';

    protected $description = 'Exportiert Lohnarten als CSV oder PDF';

    public function handle(): int
    {
        $teamId = $this->option('team-id');
        $format = $this->option('format');
        $output = $this->option('output');

        if (!$teamId) {
            $this->error('Team-ID ist erforderlich. Verwende --team-id=1');
            return 1;
        }

        if (!in_array($format, ['csv', 'pdf'])) {
            $this->error('Format muss csv oder pdf sein.');
            return 1;
        }

        $this->info("Exportiere Lohnarten für Team {$teamId} als {$format}...");

        try {
            $exportService = new PayrollTypeExportService($teamId);
            
            if ($format === 'csv') {
                $filepath = $exportService->exportToCsv();
            } else {
                $filepath = $exportService->exportToPdf();
            }

            if ($output) {
                // Datei an gewünschten Ort kopieren
                $sourcePath = storage_path('app/public/' . $filepath);
                if (file_exists($sourcePath)) {
                    copy($sourcePath, $output);
                    $this->info("Export erfolgreich: {$output}");
                } else {
                    $this->error("Export-Datei nicht gefunden: {$sourcePath}");
                    return 1;
                }
            } else {
                $this->info("Export erfolgreich: storage/app/public/{$filepath}");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Export fehlgeschlagen: " . $e->getMessage());
            return 1;
        }
    }
}
