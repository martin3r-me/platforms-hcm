<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Models\HcmTariffLevel;
use Carbon\Carbon;

class TariffProgressionService
{
    /**
     * Process tariff level progressions for all contracts
     */
    public function processProgressions(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();
        $results = [
            'processed' => 0,
            'progressed' => 0,
            'errors' => []
        ];

        $contracts = HcmEmployeeContract::with(['tariffGroup', 'tariffLevel'])
            ->whereNotNull('tariff_group_id')
            ->whereNotNull('tariff_level_id')
            ->where('is_active', true)
            ->get();

        foreach ($contracts as $contract) {
            try {
                if ($this->shouldProgress($contract, $date)) {
                    $this->progressToNextLevel($contract, $date);
                    $results['progressed']++;
                }
                $results['processed']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Check if a contract should progress to next tariff level
     */
    public function shouldProgress(HcmEmployeeContract $contract, ?string $date = null): bool
    {
        if (!$contract->tariffLevel || $contract->tariffLevel->isFinalLevel()) {
            return false;
        }

        $date = $date ?? now()->toDateString();
        
        // Check if progression is due
        if ($contract->isTariffProgressionDue($date)) {
            return true;
        }

        // Calculate if enough time has passed
        $startDate = $contract->tariff_level_start_date ?? $contract->start_date;
        $monthsSinceStart = Carbon::parse($startDate)->diffInMonths(Carbon::parse($date));
        
        return $monthsSinceStart >= $contract->tariffLevel->progression_months;
    }

    /**
     * Progress contract to next tariff level
     */
    public function progressToNextLevel(HcmEmployeeContract $contract, ?string $date = null): void
    {
        $date = $date ?? now()->toDateString();
        $nextLevel = $contract->tariffLevel->getNextLevel();

        if (!$nextLevel) {
            throw new \Exception('No next tariff level available');
        }

        // Update contract with new level
        $contract->update([
            'tariff_level_id' => $nextLevel->id,
            'tariff_level_start_date' => $date,
            'next_tariff_level_date' => $this->calculateNextProgressionDate($nextLevel, $date)
        ]);
    }

    /**
     * Calculate next progression date for a tariff level
     */
    public function calculateNextProgressionDate(HcmTariffLevel $level, string $startDate): ?string
    {
        if ($level->isFinalLevel()) {
            return null;
        }

        return Carbon::parse($startDate)
            ->addMonths($level->progression_months)
            ->toDateString();
    }

    /**
     * Initialize tariff progression for a contract
     */
    public function initializeProgression(HcmEmployeeContract $contract): void
    {
        if (!$contract->tariffLevel) {
            return;
        }

        $startDate = $contract->tariff_level_start_date ?? $contract->start_date;
        
        $contract->update([
            'tariff_level_start_date' => $startDate,
            'next_tariff_level_date' => $this->calculateNextProgressionDate($contract->tariffLevel, $startDate)
        ]);
    }

    /**
     * Get contracts due for progression
     */
    public function getContractsDueForProgression(?string $date = null): \Illuminate\Database\Eloquent\Collection
    {
        $date = $date ?? now()->toDateString();
        
        return HcmEmployeeContract::with(['tariffGroup', 'tariffLevel', 'employee'])
            ->whereNotNull('tariff_group_id')
            ->whereNotNull('tariff_level_id')
            ->where('is_active', true)
            ->where('next_tariff_level_date', '<=', $date)
            ->get();
    }

    /**
     * Import tariff logic from CSV data
     */
    public function importTariffLogic(array $csvData, int $tariffAgreementId): array
    {
        $results = [
            'tariff_groups' => 0,
            'tariff_levels' => 0,
            'errors' => []
        ];

        foreach ($csvData as $row) {
            try {
                // Find or create tariff group
                $tariffGroup = \Platform\Hcm\Models\HcmTariffGroup::firstOrCreate([
                    'tariff_agreement_id' => $tariffAgreementId,
                    'code' => $row['TKZ']
                ], [
                    'name' => $row['TKZ'] // You might want to map this to a proper name
                ]);

                // Find or create tariff level
                $tariffLevel = \Platform\Hcm\Models\HcmTariffLevel::firstOrCreate([
                    'tariff_group_id' => $tariffGroup->id,
                    'code' => $row['Stufe']
                ], [
                    'name' => "Stufe {$row['Stufe']}",
                    'progression_months' => $row['Zugehörigkeit'] === '999' ? 999 : (int)$row['Zugehörigkeit']
                ]);

                $results['tariff_groups']++;
                $results['tariff_levels']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'row' => $row,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
