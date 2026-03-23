<?php

namespace Platform\Hcm\Livewire\InterviewSchedule;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmInterview;
use Platform\Hcm\Models\HcmInterviewType;
use Platform\Hcm\Models\HcmJobTitle;

class Index extends Component
{
    public $search = '';
    public $filterType = '';
    public $filterJobTitle = '';
    public $filterStatus = 'all';

    public $showCreateModal = false;
    public $showEditModal = false;
    public $editingId = null;

    public $title = '';
    public $description = '';
    public $interview_type_id = '';
    public $hcm_job_title_id = '';
    public $location = '';
    public $starts_at = '';
    public $ends_at = '';
    public $min_participants = null;
    public $max_participants = null;
    public $status = 'planned';
    public $is_active = true;
    public $selectedInterviewers = [];

    protected $rules = [
        'title' => 'nullable|string|max:255',
        'description' => 'nullable|string',
        'interview_type_id' => 'nullable|integer|exists:hcm_interview_types,id',
        'hcm_job_title_id' => 'nullable|integer|exists:hcm_job_titles,id',
        'location' => 'nullable|string|max:255',
        'starts_at' => 'required|date',
        'ends_at' => 'nullable|date|after_or_equal:starts_at',
        'min_participants' => 'nullable|integer|min:0',
        'max_participants' => 'nullable|integer|min:1',
        'status' => 'required|in:planned,confirmed,cancelled,completed',
        'is_active' => 'boolean',
        'selectedInterviewers' => 'array',
    ];

    public function render()
    {
        return view('hcm::livewire.interview-schedule.index')
            ->layout('platform::layouts.app');
    }

    #[Computed]
    public function interviews()
    {
        return HcmInterview::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('location', 'like', '%' . $this->search . '%')
                        ->orWhereHas('interviewType', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                        ->orWhereHas('jobTitle', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->filterType, fn($q) => $q->where('interview_type_id', $this->filterType))
            ->when($this->filterJobTitle, fn($q) => $q->where('hcm_job_title_id', $this->filterJobTitle))
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('status', $this->filterStatus))
            ->with(['interviewType', 'jobTitle', 'interviewers', 'bookings'])
            ->orderBy('starts_at', 'desc')
            ->get();
    }

    #[Computed]
    public function interviewTypes()
    {
        return HcmInterviewType::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function jobTitles()
    {
        return HcmJobTitle::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function teamUsers()
    {
        return auth()->user()->currentTeam->allUsers()->sortBy('name')->values();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $m = HcmInterview::with('interviewers')->findOrFail($id);
        $this->editingId = $m->id;
        $this->title = $m->title ?? '';
        $this->description = $m->description ?? '';
        $this->interview_type_id = $m->interview_type_id ?? '';
        $this->hcm_job_title_id = $m->hcm_job_title_id ?? '';
        $this->location = $m->location ?? '';
        $this->starts_at = $m->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = $m->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->min_participants = $m->min_participants;
        $this->max_participants = $m->max_participants;
        $this->status = $m->status;
        $this->is_active = $m->is_active;
        $this->selectedInterviewers = $m->interviewers->pluck('id')->toArray();
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title' => $this->title ?: null,
            'description' => $this->description ?: null,
            'interview_type_id' => $this->interview_type_id ?: null,
            'hcm_job_title_id' => $this->hcm_job_title_id ?: null,
            'location' => $this->location ?: null,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at ?: null,
            'min_participants' => $this->min_participants,
            'max_participants' => $this->max_participants,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'team_id' => auth()->user()->currentTeam->id,
        ];

        if ($this->editingId) {
            $m = HcmInterview::findOrFail($this->editingId);
            $m->update($data);
            $m->interviewers()->sync($this->selectedInterviewers);
            session()->flash('success', 'Termin erfolgreich aktualisiert!');
        } else {
            $data['created_by_user_id'] = auth()->id();
            $m = HcmInterview::create($data);
            $m->interviewers()->sync($this->selectedInterviewers);
            session()->flash('success', 'Termin erfolgreich erstellt!');
        }

        $this->closeModals();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $m = HcmInterview::findOrFail($id);
        $m->delete();
        session()->flash('success', 'Termin erfolgreich gelöscht!');
    }

    public function closeModals(): void
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->editingId = null;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->description = '';
        $this->interview_type_id = '';
        $this->hcm_job_title_id = '';
        $this->location = '';
        $this->starts_at = '';
        $this->ends_at = '';
        $this->min_participants = null;
        $this->max_participants = null;
        $this->status = 'planned';
        $this->is_active = true;
        $this->selectedInterviewers = [];
    }
}
