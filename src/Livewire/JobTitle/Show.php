<?php

namespace Platform\Hcm\Livewire\JobTitle;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Core\Livewire\Concerns\WithExtraFields;
use Illuminate\Support\Facades\Auth;
use Platform\Hcm\Models\HcmJobTitle;

class Show extends Component
{
    use WithExtraFields;

    public HcmJobTitle $jobTitle;

    public function mount(HcmJobTitle $jobTitle)
    {
        $this->jobTitle = $jobTitle->load(['onboardings']);
        $this->loadExtraFieldValues($this->jobTitle);
    }

    public function rules(): array
    {
        return array_merge([
            'jobTitle.code' => 'required|string|max:64',
            'jobTitle.name' => 'required|string|max:255',
            'jobTitle.is_active' => 'boolean',
            'jobTitle.owned_by_user_id' => 'nullable|exists:users,id',
        ], $this->getExtraFieldValidationRules());
    }

    public function messages(): array
    {
        return $this->getExtraFieldValidationMessages();
    }

    public function save(): void
    {
        $this->validate();
        $this->jobTitle->save();
        $this->saveExtraFieldValues($this->jobTitle);
        session()->flash('message', 'Stellenbezeichnung erfolgreich aktualisiert.');
    }

    public function deleteJobTitle(): void
    {
        $this->jobTitle->delete();
        session()->flash('message', 'Stellenbezeichnung erfolgreich gelöscht.');
        $this->redirect(route('hcm.job-titles.index'), navigate: true);
    }

    #[Computed]
    public function teamUsers()
    {
        return Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->fullname ?? $user->name,
            ]);
    }

    #[Computed]
    public function isDirty()
    {
        return $this->jobTitle->isDirty() || $this->isExtraFieldsDirty();
    }

    public function rendered(): void
    {
        $this->dispatch('extrafields', [
            'context_type' => HcmJobTitle::class,
            'context_id' => $this->jobTitle->id,
        ]);
    }

    public function render()
    {
        return view('hcm::livewire.job-title.show')
            ->layout('platform::layouts.app');
    }
}
