<?php

namespace Platform\Hcm\Services;

use Platform\Finance\Models\FinanceAccount;

class FinanceAccountService
{
    /**
     * Get active finance accounts for the current team
     * Loose coupling: Uses Finance module models directly
     */
    public static function getAccountsForTeam(?int $teamId): array
    {
        if (!$teamId) {
            return [];
        }

        return FinanceAccount::forTeam($teamId)
            ->active()
            ->valid()
            ->orderBy('number')
            ->get()
            ->mapWithKeys(function ($account) {
                $label = $account->number . ' - ' . $account->name;
                return [$account->id => $label];
            })
            ->toArray();
    }

    /**
     * Find finance account by ID
     */
    public static function findAccount(?int $accountId): ?FinanceAccount
    {
        if (!$accountId) {
            return null;
        }

        return FinanceAccount::find($accountId);
    }
}

