<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Models\HcmTariffAgreement;
use Platform\Hcm\Services\TariffProgressionService;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportTariffLogic extends Command
{
    protected $signature = 'hcm:import-tariff-logic 
                            {file : Path to CSV file}
                            {--tariff-agreement= : Tariff agreement ID or code}
                            {--create-agreement : Create new tariff agreement if not exists}';

    protected $description = 'Import tariff logic from CSV file';

    public function handle(TariffProgressionService $progressionService): int
    {
        $filePath = $this->argument('file');
        $tariffAgreementId = $this->option('tariff-agreement');
        $createAgreement = $this->option('create-agreement');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Parse CSV
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $records = Statement::create()->process($csv);

        // Get or create tariff agreement
        $tariffAgreement = $this->getTariffAgreement($tariffAgreementId, $createAgreement);
        if (!$tariffAgreement) {
            $this->error('Tariff agreement not found and --create-agreement not specified');
            return 1;
        }

        $this->info("Importing tariff logic for: {$tariffAgreement->name}");

        // Convert CSV records to array
        $csvData = [];
        foreach ($records as $record) {
            $csvData[] = [
                'TV' => $record['TV'] ?? '',
                'TKZ' => $record['TKZ'] ?? '',
                'Stufe' => $record['Stufe'] ?? '',
                'Jahr' => $record['Jahr'] ?? '',
                'Monat' => $record['Monat'] ?? '',
                'Zugehörigkeit' => $record['Zugehörigkeit'] ?? '',
                'FolgeTKZ' => $record['FolgeTKZ'] ?? '',
                'FolgeStufe' => $record['FolgeStufe'] ?? '',
                'Stufungsmonat' => $record['Stufungsmonat'] ?? '',
            ];
        }

        // Import tariff logic
        $results = $progressionService->importTariffLogic($csvData, $tariffAgreement->id);

        $this->info("Import completed:");
        $this->line("- Tariff groups created/updated: {$results['tariff_groups']}");
        $this->line("- Tariff levels created/updated: {$results['tariff_levels']}");

        if (!empty($results['errors'])) {
            $this->warn("Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->line("- " . $error['error']);
            }
        }

        return 0;
    }

    private function getTariffAgreement(?string $identifier, bool $createIfNotExists): ?HcmTariffAgreement
    {
        if (!$identifier) {
            $this->error('Tariff agreement identifier is required');
            return null;
        }

        // Try to find by ID first
        if (is_numeric($identifier)) {
            $agreement = HcmTariffAgreement::find($identifier);
            if ($agreement) {
                return $agreement;
            }
        }

        // Try to find by code
        $agreement = HcmTariffAgreement::where('code', $identifier)->first();
        if ($agreement) {
            return $agreement;
        }

        // Create new agreement if requested
        if ($createIfNotExists) {
            $name = $this->ask('Enter name for new tariff agreement');
            if (!$name) {
                $this->error('Name is required for new tariff agreement');
                return null;
            }

            return HcmTariffAgreement::create([
                'code' => $identifier,
                'name' => $name,
                'description' => 'Imported from CSV',
                'team_id' => 1, // You might want to get this from context
                'is_active' => true,
            ]);
        }

        return null;
    }
}
