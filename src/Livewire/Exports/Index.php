<?php

namespace Platform\Hcm\Livewire\Exports;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmExport;
use Platform\Hcm\Models\HcmExportTemplate;
use Platform\Hcm\Services\HcmExportService;
use Illuminate\Support\Facades\Storage;

class Index extends Component
{
    public $search = '';
    public $filterType = '';
    public $filterStatus = '';
    
    // Export-Trigger
    public $selectedExportType = '';
    public $selectedEmployerId = null;
    public $showExportModal = false;

    public function triggerExport(string $type): void
    {
        $this->selectedExportType = $type;
        $this->selectedEmployerId = null;
        $this->showExportModal = true;
    }

    public function executeExport(): void
    {
        $rules = [
            'selectedExportType' => 'required|string|in:infoniqa,payroll,employees',
        ];
        
        // Für INFONIQA-Export ist employer_id erforderlich
        if ($this->selectedExportType === 'infoniqa') {
            $rules['selectedEmployerId'] = 'required|exists:hcm_employers,id';
        }
        
        $this->validate($rules);

        try {
            $teamId = auth()->user()->currentTeam->id;
            $userId = auth()->id();
            
            $service = new HcmExportService($teamId, $userId);
            
            $name = match($this->selectedExportType) {
                'infoniqa' => 'INFONIQA Export',
                'payroll' => 'Lohnarten Export',
                'employees' => 'Mitarbeiter Export',
                default => 'Export',
            };
            
            // Parameter für INFONIQA-Export
            $parameters = null;
            if ($this->selectedExportType === 'infoniqa') {
                $parameters = ['employer_id' => $this->selectedEmployerId];
            }
            
            $export = $service->createExport(
                name: $name,
                type: $this->selectedExportType,
                format: 'csv',
                parameters: $parameters
            );
            
            // Export asynchron ausführen (oder synchron je nach Bedarf)
            $filepath = $service->executeExport($export);
            
            $this->showExportModal = false;
            $this->selectedExportType = '';
            $this->selectedEmployerId = null;
            
            session()->flash('success', 'Export erfolgreich erstellt!');
        } catch (\Throwable $e) {
            session()->flash('error', 'Export fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function cancelExport(): void
    {
        $this->showExportModal = false;
        $this->selectedExportType = '';
        $this->selectedEmployerId = null;
    }

    public function downloadExport(int $exportId)
    {
        $export = HcmExport::findOrFail($exportId);
        
        if (!$export->file_path || !Storage::disk('public')->exists($export->file_path)) {
            session()->flash('error', 'Export-Datei nicht gefunden');
            return;
        }

        return Storage::disk('public')->download($export->file_path, $export->file_name);
    }

    public function deleteExport(HcmExport $export): void
    {
        if ($export->file_path && Storage::disk('public')->exists($export->file_path)) {
            Storage::disk('public')->delete($export->file_path);
        }
        
        $export->delete();
        
        session()->flash('success', 'Export gelöscht');
    }

    #[Computed]
    public function exports()
    {
        $query = HcmExport::where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('created_at', 'desc');

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('type', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterType !== '') {
            $query->where('type', $this->filterType);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        return $query->get();
    }

    #[Computed]
    public function statistics()
    {
        $teamId = auth()->user()->currentTeam->id;
        
        return [
            'total' => HcmExport::where('team_id', $teamId)->count(),
            'completed' => HcmExport::where('team_id', $teamId)->where('status', 'completed')->count(),
            'pending' => HcmExport::where('team_id', $teamId)->where('status', 'pending')->count(),
            'processing' => HcmExport::where('team_id', $teamId)->where('status', 'processing')->count(),
            'failed' => HcmExport::where('team_id', $teamId)->where('status', 'failed')->count(),
        ];
    }

    #[Computed]
    public function exportTypes()
    {
        return [
            'infoniqa' => 'INFONIQA Export',
            'payroll' => 'Lohnarten Export',
            'employees' => 'Mitarbeiter Export',
        ];
    }

    #[Computed]
    public function employers()
    {
        return \Platform\Hcm\Models\HcmEmployer::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('employer_number')
            ->get()
            ->mapWithKeys(fn($employer) => [$employer->id => $employer->display_name]);
    }

    public function render()
    {
        return view('hcm::livewire.exports.index')
            ->layout('platform::layouts.app');
    }
}

