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
    public $sortField = 'code';
    public $sortDirection = 'asc';

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function getTariffLevelsProperty()
    {
        return HcmTariffLevel::query()
            ->with(['tariffGroup.tariffAgreement'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%')
                      ->orWhereHas('tariffGroup', function ($groupQuery) {
                          $groupQuery->where('code', 'like', '%' . $this->search . '%')
                                    ->orWhere('name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('hcm::livewire.tariff-level.index', [
            'tariffLevels' => $this->tariffLevels
        ])->layout('platform::layouts.app');
    }
}