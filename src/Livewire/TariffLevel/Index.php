<?php

namespace Platform\Hcm\Livewire\TariffLevel;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmTariffLevel;

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
        $tariffLevels = HcmTariffLevel::with(['tariffGroup.tariffAgreement'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('hcm::livewire.tariff-level.index', [
            'tariffLevels' => $tariffLevels,
        ])->layout('platform::layouts.app');
    }
}
