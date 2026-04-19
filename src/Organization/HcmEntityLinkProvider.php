<?php

namespace Platform\Hcm\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Organization\Contracts\EntityLinkProvider;

class HcmEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['hcm_employee'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'hcm_employee' => ['label' => 'Mitarbeiter', 'singular' => 'Mitarbeiter', 'icon' => 'user', 'route' => null],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        // No eager loading needed
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return [
            'is_active' => (bool) ($model->is_active ?? false),
            'employee_number' => $model->employee_number ?? null,
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            'hcm_employee' => [
                ['field' => 'employee_number', 'format' => 'prefixed_text', 'prefix' => '#'],
                ['field' => 'is_active', 'format' => 'boolean_active'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'hcm_employee') {
            return [];
        }

        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        $employees = HcmEmployee::whereIn('id', $allIds)
            ->select('id', 'is_active')
            ->get()
            ->keyBy('id');

        $today = now()->toDateString();
        $activeContractCounts = DB::table('hcm_employee_contracts')
            ->whereIn('employee_id', $allIds)
            ->where('start_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->groupBy('employee_id')
            ->pluck(DB::raw('count(*)'), 'employee_id')
            ->all();

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $active = 0;
            $contractsActive = 0;

            foreach ($ids as $id) {
                $employee = $employees[$id] ?? null;
                if (!$employee) {
                    continue;
                }
                $total++;
                if ($employee->is_active) {
                    $active++;
                }
                $contractsActive += (int) ($activeContractCounts[$id] ?? 0);
            }

            $result[$entityId] = [
                'hcm_employees_total' => $total,
                'hcm_employees_active' => $active,
                'hcm_contracts_active' => $contractsActive,
            ];
        }

        return $result;
    }
}
