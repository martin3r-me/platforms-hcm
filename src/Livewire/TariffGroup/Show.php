<?php

namespace Platform\Hcm\Livewire\TariffGroup;

use Livewire\Component;
use Platform\Hcm\Models\HcmTariffGroup;

class Show extends Component
{
    public HcmTariffGroup $tariffGroup;

    public function mount(HcmTariffGroup $tariffGroup)
    {
        $this->tariffGroup = $tariffGroup->load(['tariffAgreement', 'tariffLevels', 'tariffRates']);
    }

    public function getProgressionStatsProperty()
    {
        $levels = $this->tariffGroup->tariffLevels;
        
        $avgProgression = $levels->where('progression_months', '!=', 999)->avg('progression_months');
        
        return [
            'possible_progressions' => $levels->filter(function($level) { 
                return !$level->isFinalLevel(); 
            })->count(),
            'final_levels' => $levels->filter(function($level) { 
                return $level->isFinalLevel(); 
            })->count(),
            'avg_progression_months' => $avgProgression ? (float)$avgProgression : 0,
        ];
    }

    public function render()
    {
        return view('hcm::livewire.tariff-group.show', [
            'tariffGroup' => $this->tariffGroup,
        ])->layout('platform::layouts.app');
    }
}
