<?php

namespace Platform\Hcm\Organization;

use Illuminate\Database\Eloquent\Builder;
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
        return [];
    }
}
