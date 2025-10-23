<?php

namespace Platform\Hcm\Services;

use Platform\Hcm\Models\HcmEmployeeContract;
use Platform\Hcm\Models\HcmTariffLevel;
use Platform\Hcm\Models\HcmTariffProgression;
use Carbon\Carbon;

class TariffProgressionService
{
    /**
     * Process tariff level progressions for all contracts (retroactive by default)
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
                // Check both current and retroactive progressions
                if ($this->shouldProgress($contract, $date) || $this->shouldProgressRetroactively($contract, $date)) {
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
        $commandDate = $date ?? now()->toDateString();
        $nextLevel = $contract->tariffLevel->getNextLevel();

        if (!$nextLevel) {
            throw new \Exception('No next tariff level available');
        }

        // Calculate the actual progression date (when the jump should have happened)
        $actualProgressionDate = $this->calculateActualProgressionDate($contract, $commandDate);

        // Create progression record with the actual progression date
        HcmTariffProgression::create([
            'employee_contract_id' => $contract->id,
            'from_tariff_level_id' => $contract->tariff_level_id,
            'to_tariff_level_id' => $nextLevel->id,
            'progression_date' => $actualProgressionDate,
            'progression_reason' => 'automatic',
            'progression_notes' => 'Automatische Tarifstufen-Progression'
        ]);

        // Update contract with new level
        $contract->update([
            'tariff_level_id' => $nextLevel->id,
            'tariff_level_start_date' => $actualProgressionDate,
            'next_tariff_level_date' => $this->calculateNextProgressionDate($nextLevel, $actualProgressionDate)
        ]);
        
        // Ensure next tariff level date is properly set for the new level
        $contract->setNextTariffLevelDate();
    }

    /**
     * Calculate the actual progression date (when the jump should have happened)
     */
    public function calculateActualProgressionDate(HcmEmployeeContract $contract, string $commandDate): string
    {
        $startDate = $contract->tariff_level_start_date ?? $contract->start_date;
        $progressionMonths = $contract->tariffLevel->progression_months;
        
        // Calculate when the progression should have happened
        $progressionDate = Carbon::parse($startDate)
            ->addMonths($progressionMonths)
            ->toDateString();
            
        // If the progression date is in the future, use the command date
        // If it's in the past, use the calculated progression date
        return Carbon::parse($progressionDate)->isFuture() 
            ? $commandDate 
            : $progressionDate;
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

    /**
     * Manually progress a contract to a specific tariff level
     */
    public function manualProgression(
        HcmEmployeeContract $contract, 
        HcmTariffLevel $targetLevel, 
        string $date, 
        string $reason = 'manual',
        ?string $notes = null
    ): HcmTariffProgression {
        // For manual progression, use the provided date as-is
        $progressionDate = $date;

        // Create progression record
        $progression = HcmTariffProgression::create([
            'employee_contract_id' => $contract->id,
            'from_tariff_level_id' => $contract->tariff_level_id,
            'to_tariff_level_id' => $targetLevel->id,
            'progression_date' => $progressionDate,
            'progression_reason' => $reason,
            'progression_notes' => $notes ?? "Manuelle Progression zu {$targetLevel->name}"
        ]);

        // Update contract
        $contract->update([
            'tariff_level_id' => $targetLevel->id,
            'tariff_level_start_date' => $progressionDate,
            'next_tariff_level_date' => $this->calculateNextProgressionDate($targetLevel, $progressionDate)
        ]);

        return $progression;
    }

    /**
     * Get progression history for a contract
     */
    public function getProgressionHistory(HcmEmployeeContract $contract): \Illuminate\Database\Eloquent\Collection
    {
        return $contract->tariffProgressions()
            ->with(['fromTariffLevel', 'toTariffLevel'])
            ->orderBy('progression_date', 'desc')
            ->get();
    }

    /**
     * Process only current progressions (not retroactive)
     */
    public function processCurrentProgressions(?string $date = null): array
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
                // Check only current progressions (not retroactive)
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
     * Process retroactive progressions (for contracts that should have progressed earlier)
     */
    public function processRetroactiveProgressions(?string $date = null): array
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
                if ($this->shouldProgressRetroactively($contract, $date)) {
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
     * Check if a contract should progress retroactively
     */
    public function shouldProgressRetroactively(HcmEmployeeContract $contract, string $date): bool
    {
        if (!$contract->tariffLevel || $contract->tariffLevel->isFinalLevel()) {
            return false;
        }

        $startDate = $contract->tariff_level_start_date ?? $contract->start_date;
        $progressionMonths = $contract->tariffLevel->progression_months;
        
        // Calculate when the progression should have happened
        $progressionDate = Carbon::parse($startDate)
            ->addMonths($progressionMonths)
            ->toDateString();
            
        // Check if the progression date is in the past
        return Carbon::parse($progressionDate)->isPast();
    }
}
