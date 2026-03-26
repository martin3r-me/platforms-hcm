<?php

namespace Platform\Hcm\Livewire\OnboardingContracts;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmContractTemplate;
use Platform\Hcm\Models\HcmOnboardingContract;

class Index extends Component
{
    public $search = '';
    public $filterStatus = 'all';
    public $filterTemplateId = 'all';

    public function render()
    {
        return view('hcm::livewire.onboarding-contracts.index')
            ->layout('platform::layouts.app');
    }

    #[Computed]
    public function contracts()
    {
        $teamId = auth()->user()->currentTeam->id;

        return HcmOnboardingContract::query()
            ->with(['contractTemplate', 'onboarding.crmContactLinks.contact'])
            ->where('team_id', $teamId)
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('notes', 'like', '%' . $this->search . '%')
                        ->orWhereHas('onboarding.crmContactLinks.contact', function ($query) {
                            $query->where('first_name', 'like', '%' . $this->search . '%')
                                ->orWhere('last_name', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('contractTemplate', function ($query) {
                            $query->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterTemplateId !== 'all', fn($q) => $q->where('hcm_contract_template_id', $this->filterTemplateId))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function templates()
    {
        $teamId = auth()->user()->currentTeam->id;

        return HcmContractTemplate::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
