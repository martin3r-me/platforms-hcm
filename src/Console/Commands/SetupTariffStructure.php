<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Platform\Hcm\Services\TariffStructureService;
use Platform\Hcm\Models\HcmTariffAgreement;

class SetupTariffStructure extends Command
{
    protected $signature = 'hcm:setup-tariff-structure 
                            {--tariff-agreement= : Tariff agreement ID or code}
                            {--create-agreement : Create new tariff agreement}
                            {--team-id= : Team ID for tariff agreement}
                            {--ensure-contracts : Ensure all contracts have tariff assignment}';

    protected $description = 'Setup complete tariff structure including AT groups';

    public function handle(TariffStructureService $structureService): int
    {
        $tariffAgreementId = $this->option('tariff-agreement');
        $createAgreement = $this->option('create-agreement');
        $teamId = $this->option('team-id');
        $ensureContracts = $this->option('ensure-contracts');

        // Get team ID
        if (!$teamId) {
            $teamId = $this->askForTeamId();
            if (!$teamId) {
                $this->error('Team ID is required');
                return 1;
            }
        }

        // Get or create tariff agreement
        $tariffAgreement = $this->getTariffAgreement($tariffAgreementId, $createAgreement, $teamId);
        if (!$tariffAgreement) {
            $this->error('Tariff agreement not found and --create-agreement not specified');
            return 1;
        }

        $this->info("Setting up tariff structure for: {$tariffAgreement->name}");

        // Create standard structure
        $results = $structureService->createStandardStructure($tariffAgreement->id);

        $this->info("Tariff structure created:");
        $this->line("- Tariff groups: {$results['tariff_groups']}");
        $this->line("- Tariff levels: {$results['tariff_levels']}");
        $this->line("- Tariff rates: {$results['tariff_rates']}");

        // Ensure all contracts have tariff assignment
        if ($ensureContracts) {
            $this->info("Ensuring all contracts have tariff assignment...");
            
            $contractResults = $structureService->ensureAllContractsHaveTariff($teamId);
            
            $this->line("- Contracts processed: {$contractResults['processed']}");
            $this->line("- Contracts assigned: {$contractResults['assigned']}");

            if (!empty($contractResults['errors'])) {
                $this->warn("Errors encountered:");
                foreach ($contractResults['errors'] as $error) {
                    $this->line("- Contract {$error['contract_id']}: {$error['error']}");
                }
            }
        }

        $this->info("Tariff structure setup completed!");
        return 0;
    }

    private function getTariffAgreement(?string $identifier, bool $createIfNotExists, int $teamId): ?HcmTariffAgreement
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
                'description' => 'Standard tariff structure with AT groups',
                'team_id' => $teamId,
                'is_active' => true,
            ]);
        }

        return null;
    }

    private function askForTeamId(): ?int
    {
        // Get available teams
        $teams = \Platform\Core\Models\Team::all();
        
        if ($teams->isEmpty()) {
            $this->error('No teams found');
            return null;
        }

        $this->info('Available teams:');
        foreach ($teams as $team) {
            $this->line("- {$team->id}: {$team->name}");
        }

        $teamId = $this->ask('Enter Team ID');
        
        if (!is_numeric($teamId)) {
            $this->error('Team ID must be numeric');
            return null;
        }

        $team = $teams->find($teamId);
        if (!$team) {
            $this->error("Team with ID {$teamId} not found");
            return null;
        }

        return (int) $teamId;
    }
}
