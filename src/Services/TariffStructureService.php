<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmTariffAgreement;
use Platform\Hcm\Models\HcmTariffGroup;
use Platform\Hcm\Models\HcmTariffLevel;
use Platform\Hcm\Models\HcmTariffRate;

class TariffStructureService
{
    /**
     * Create standard tariff structure including AT groups
     */
    public function createStandardStructure(int $tariffAgreementId): array
    {
        $results = [
            'tariff_groups' => 0,
            'tariff_levels' => 0,
            'tariff_rates' => 0,
        ];

        // Standard tariff groups from CSV
        $standardGroups = [
            'T1' => 'Entgeltgruppe T1',
            'T2' => 'Entgeltgruppe T2', 
            'T3' => 'Entgeltgruppe T3',
            'LJ1' => 'Lehrjahr 1',
            'LJ2' => 'Lehrjahr 2',
            'LJ3' => 'Lehrjahr 3',
        ];

        // AT groups (Außertariflich)
        $atGroups = [
            'AT' => 'Außertariflich Standard',
            'AUS' => 'Außertariflich Spezial',
            'AT-MGMT' => 'Außertariflich Management',
            'AT-SPEC' => 'Außertariflich Spezialisten',
        ];

        $allGroups = array_merge($standardGroups, $atGroups);

        foreach ($allGroups as $code => $name) {
            // Create tariff group
            $tariffGroup = HcmTariffGroup::firstOrCreate([
                'tariff_agreement_id' => $tariffAgreementId,
                'code' => $code,
            ], [
                'name' => $name,
            ]);

            $results['tariff_groups']++;

            // Create levels based on group type
            if (str_starts_with($code, 'AT')) {
                // AT groups: Single level with no progression
                $this->createAtLevels($tariffGroup, $results);
            } else {
                // Standard groups: Multiple levels with progression
                $this->createStandardLevels($tariffGroup, $code, $results);
            }
        }

        return $results;
    }

    /**
     * Create AT (Außertariflich) levels
     */
    private function createAtLevels(HcmTariffGroup $tariffGroup, array &$results): void
    {
        // Single level for AT groups
        $level = HcmTariffLevel::firstOrCreate([
            'tariff_group_id' => $tariffGroup->id,
            'code' => '1',
        ], [
            'name' => 'Außertariflich',
            'progression_months' => 999, // No progression
        ]);

        $results['tariff_levels']++;

        // Create base rate (can be updated later)
        HcmTariffRate::firstOrCreate([
            'tariff_group_id' => $tariffGroup->id,
            'tariff_level_id' => $level->id,
            'valid_from' => now()->toDateString(),
        ], [
            'amount' => 0.00, // Will be set individually
        ]);

        $results['tariff_rates']++;
    }

    /**
     * Create standard tariff levels with progression
     */
    private function createStandardLevels(HcmTariffGroup $tariffGroup, string $groupCode, array &$results): void
    {
        $levels = $this->getStandardLevels($groupCode);

        foreach ($levels as $levelCode => $levelData) {
            $level = HcmTariffLevel::firstOrCreate([
                'tariff_group_id' => $tariffGroup->id,
                'code' => $levelCode,
            ], [
                'name' => $levelData['name'],
                'progression_months' => $levelData['progression_months'],
            ]);

            $results['tariff_levels']++;

            // Create base rate
            HcmTariffRate::firstOrCreate([
                'tariff_group_id' => $tariffGroup->id,
                'tariff_level_id' => $level->id,
                'valid_from' => now()->toDateString(),
            ], [
                'amount' => $levelData['base_amount'],
            ]);

            $results['tariff_rates']++;
        }
    }

    /**
     * Get standard level structure based on CSV logic
     */
    private function getStandardLevels(string $groupCode): array
    {
        $levels = [];

        switch ($groupCode) {
            case 'T1':
            case 'T2':
                $levels = [
                    '1' => ['name' => 'Stufe 1', 'progression_months' => 12, 'base_amount' => 0.00],
                    '2' => ['name' => 'Stufe 2', 'progression_months' => 12, 'base_amount' => 0.00],
                    '3' => ['name' => 'Stufe 3', 'progression_months' => 30, 'base_amount' => 0.00],
                    '3,30' => ['name' => 'Stufe 3,30', 'progression_months' => 30, 'base_amount' => 0.00],
                    '3,54' => ['name' => 'Stufe 3,54', 'progression_months' => 30, 'base_amount' => 0.00],
                    '4' => ['name' => 'Stufe 4', 'progression_months' => 999, 'base_amount' => 0.00],
                ];
                break;

            case 'T3':
                $levels = [
                    '1' => ['name' => 'Stufe 1', 'progression_months' => 999, 'base_amount' => 0.00],
                    '2' => ['name' => 'Stufe 2', 'progression_months' => 999, 'base_amount' => 0.00],
                    '3' => ['name' => 'Stufe 3', 'progression_months' => 999, 'base_amount' => 0.00],
                    '4' => ['name' => 'Stufe 4', 'progression_months' => 999, 'base_amount' => 0.00],
                ];
                break;

            case 'LJ1':
            case 'LJ2':
            case 'LJ3':
                $levels = [
                    '1' => ['name' => 'Lehrjahr 1', 'progression_months' => 12, 'base_amount' => 0.00],
                    '2' => ['name' => 'Lehrjahr 2', 'progression_months' => 12, 'base_amount' => 0.00],
                    '3' => ['name' => 'Lehrjahr 3', 'progression_months' => 999, 'base_amount' => 0.00],
                ];
                break;
        }

        return $levels;
    }

    /**
     * Ensure all contracts have tariff assignment
     */
    public function ensureAllContractsHaveTariff(int $teamId): array
    {
        $results = [
            'processed' => 0,
            'assigned' => 0,
            'errors' => []
        ];

        // Get contracts without tariff assignment
        $contracts = \Platform\Hcm\Models\HcmEmployeeContract::where('team_id', $teamId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('tariff_group_id')
                      ->orWhereNull('tariff_level_id');
            })
            ->get();

        foreach ($contracts as $contract) {
            try {
                // Default to AT (Außertariflich) if no specific assignment
                $this->assignDefaultTariff($contract);
                $results['assigned']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage()
                ];
            }
            $results['processed']++;
        }

        return $results;
    }

    /**
     * Assign default AT tariff to contract
     */
    private function assignDefaultTariff(\Platform\Hcm\Models\HcmEmployeeContract $contract): void
    {
        // Find AT tariff group
        $atGroup = HcmTariffGroup::where('code', 'AT')->first();
        if (!$atGroup) {
            throw new \Exception('AT tariff group not found');
        }

        // Find AT level
        $atLevel = HcmTariffLevel::where('tariff_group_id', $atGroup->id)
            ->where('code', '1')
            ->first();

        if (!$atLevel) {
            throw new \Exception('AT tariff level not found');
        }

        // Assign AT tariff
        $contract->update([
            'tariff_group_id' => $atGroup->id,
            'tariff_level_id' => $atLevel->id,
            'tariff_assignment_date' => $contract->start_date,
            'tariff_level_start_date' => $contract->start_date,
            'next_tariff_level_date' => null, // No progression for AT
        ]);
    }
}
