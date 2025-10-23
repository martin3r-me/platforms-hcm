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

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function getTariffRatesProperty()
    {
        return HcmTariffRate::query()
            ->with(['tariffLevel.tariffGroup.tariffAgreement'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('amount', 'like', '%' . $this->search . '%')
                      ->orWhereHas('tariffLevel', function ($levelQuery) {
                          $levelQuery->where('code', 'like', '%' . $this->search . '%')
                                    ->orWhere('name', 'like', '%' . $this->search . '%')
                                    ->orWhereHas('tariffGroup', function ($groupQuery) {
                                        $groupQuery->where('code', 'like', '%' . $this->search . '%')
                                                  ->orWhere('name', 'like', '%' . $this->search . '%');
                                    });
                      });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('hcm::livewire.tariff-rate.index', [
            'tariffRates' => $this->tariffRates
        ])->layout('platform::layouts.app');
    }
}