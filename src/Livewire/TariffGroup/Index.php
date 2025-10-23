<?php

namespace Platform\Hcm\Livewire\TariffGroup;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmTariffGroup;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 15;
    public $sortField = 'name';
    public $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        $tariffGroups = HcmTariffGroup::with(['tariffAgreement', 'tariffLevels', 'tariffRates'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('hcm::livewire.tariff-group.index', [
            'tariffGroups' => $tariffGroups,
        ])->layout('platform::layouts.app');
    }
}
