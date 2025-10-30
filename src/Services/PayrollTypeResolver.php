<?php

namespace Platform\Hcm\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Platform\Hcm\Models\HcmPayrollProvider;
use Platform\Hcm\Models\HcmPayrollType;
use Platform\Hcm\Models\HcmPayrollTypeMapping;

class PayrollTypeResolver
{
    /**
     * Auflösen eines externen Lohnarten-Codes (provider + code) auf die kanonische HcmPayrollType.
     * Abwärtskompatibel: Wenn kein Mapping gefunden wird, kann optional ein Fallback auf LANR/Code erfolgen.
     */
    public function resolve(int $teamId, string $providerKey, string $externalCode, CarbonInterface|string|null $atDate = null): ?HcmPayrollType
    {
        $date = $this->normalizeDate($atDate);

        $provider = HcmPayrollProvider::query()->where('key', $providerKey)->first();
        if (!$provider) {
            return null;
        }

        $mapping = HcmPayrollTypeMapping::query()
            ->where('team_id', $teamId)
            ->where('provider_id', $provider->id)
            ->where('external_code', $externalCode)
            ->when($date, function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
                })->where(function ($q3) use ($date) {
                    $q3->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
                });
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($mapping) {
            return $mapping->payrollType;
        }

        // Abwärtskompatibler Fallback: Versuch, direkt per LANR/Code zu finden
        // (nur wenn bestehende Daten das so genutzt haben)
        $fallback = HcmPayrollType::query()
            ->where('team_id', $teamId)
            ->where(function ($q) use ($externalCode) {
                $q->where('lanr', $externalCode)
                  ->orWhere('code', $externalCode);
            })
            ->when($date, function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
                })->where(function ($q3) use ($date) {
                    $q3->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
                });
            })
            ->first();

        return $fallback;
    }

    private function normalizeDate(CarbonInterface|string|null $atDate): ?CarbonInterface
    {
        if ($atDate === null) {
            return null;
        }
        if ($atDate instanceof CarbonInterface) {
            return $atDate;
        }
        return Carbon::parse($atDate);
    }
}


