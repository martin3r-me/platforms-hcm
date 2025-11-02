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
    public $modalShow = false;
    
    // Error-Modal
    public $showErrorModal = false;
    public $errorDetails = null;

    public function mount()
    {
        // Keine Vorauswahl beim Mount - wird erst beim Öffnen des Modals gesetzt
        // Flash-Messages nur einmal beim ersten Laden anzeigen, dann nicht mehr automatisch löschen
    }

    public function triggerExport(string $type): void
    {
        // Flash-Messages und Validierungsfehler löschen beim Öffnen des Modals
        session()->forget(['success', 'error']);
        $this->resetValidation();
        
        $this->selectedExportType = $type;
        $this->selectedEmployerId = null;
        $this->modalShow = true;
    }
    
    public function updatedModalShow($value): void
    {
        // Wenn Modal geschlossen wird, Validierungsfehler zurücksetzen
        if (!$value) {
            $this->resetValidation();
            session()->forget(['success', 'error']);
        }
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

        // Modal sofort schließen
        $this->modalShow = false;
        
        // Werte zwischenspeichern für Export
        $exportType = $this->selectedExportType;
        $employerId = $this->selectedEmployerId;
        
        // Formular zurücksetzen
        $this->selectedExportType = !empty($this->exportTypes) ? array_key_first($this->exportTypes) : '';
        $this->selectedEmployerId = null;

        try {
            $teamId = auth()->user()->currentTeam->id;
            $userId = auth()->id();
            
            $service = new HcmExportService($teamId, $userId);
            
            $name = match($exportType) {
                'infoniqa' => 'INFONIQA Export',
                'payroll' => 'Lohnarten Export',
                'employees' => 'Mitarbeiter Export',
                default => 'Export',
            };
            
            // Parameter für INFONIQA-Export
            $parameters = null;
            if ($exportType === 'infoniqa') {
                $parameters = ['employer_id' => $employerId];
            }
            
            $export = $service->createExport(
                name: $name,
                type: $exportType,
                format: 'csv',
                parameters: $parameters
            );
            
            // Export synchron ausführen (Fehler werden in DB gespeichert)
            $filepath = $service->executeExport($export);
            
            session()->flash('success', 'Export erfolgreich erstellt!');
        } catch (\Throwable $e) {
            // Fehler wird bereits im Service in der DB gespeichert
            session()->flash('error', 'Export fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function cancelExport(): void
    {
        $this->modalShow = false;
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
        
        // Alte Flash-Messages löschen und neue setzen
        session()->forget(['success', 'error']);
        session()->flash('success', 'Export gelöscht');
    }
    
    public function showErrorDetails(HcmExport $export): void
    {
        $this->errorDetails = $export->error_message;
        $this->showErrorModal = true;
    }
    
    public function closeErrorModal(): void
    {
        $this->showErrorModal = false;
        $this->errorDetails = null;
    }
    
    public function clearFlashMessages(): void
    {
        session()->forget(['success', 'error']);
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

