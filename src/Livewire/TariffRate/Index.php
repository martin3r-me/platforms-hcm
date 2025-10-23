<?php

namespace Platform\Hcm\Livewire\TariffRate;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Hcm\Models\HcmTariffRate;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 15;
    public $sortField = 'amount';
    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'amount'],
        'sortDirection' => ['except' => 'desc'],
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
        $tariffRates = HcmTariffRate::with(['tariffGroup.tariffAgreement', 'tariffLevel'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('tariffGroup', function ($groupQuery) {
                        $groupQuery->where('name', 'like', '%' . $this->search . '%')
                                  ->orWhere('code', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('tariffLevel', function ($levelQuery) {
                        $levelQuery->where('name', 'like', '%' . $this->search . '%')
                                  ->orWhere('code', 'like', '%' . $this->search . '%');
                    });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('hcm::livewire.tariff-rate.index', [
            'tariffRates' => $tariffRates,
        ])->layout('platform::layouts.app');
    }
}
