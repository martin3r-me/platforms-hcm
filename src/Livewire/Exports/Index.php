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

    // Mitarbeiter-Export Felder
    public $selectedEmployeeFields = [];
    
    // Error-Modal
    public $showErrorModal = false;
    public $errorDetails = null;
    
    public function boot(): void
    {
        // Sicherstellen, dass Error-Modal beim Initialisieren geschlossen ist
        $this->showErrorModal = false;
        $this->errorDetails = null;
    }

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

        // Sicherstellen, dass Error-Modal geschlossen ist
        $this->showErrorModal = false;
        $this->errorDetails = null;

        $this->selectedExportType = $type;
        $this->selectedEmployerId = null;

        // Bei Mitarbeiter-Export: Standard-Felder vorauswählen
        if ($type === 'employees') {
            $this->selectedEmployeeFields = ['employee_number', 'last_name', 'first_name'];
        } else {
            $this->selectedEmployeeFields = [];
        }

        $this->modalShow = true;
    }
    
    public function updatedModalShow($value): void
    {
        // Wenn Modal geöffnet wird, sicherstellen dass Error-Modal geschlossen ist
        if ($value) {
            $this->showErrorModal = false;
            $this->errorDetails = null;
            session()->forget(['success', 'error']);
            $this->resetValidation();
        }
    }

    public function executeExport(): void
    {
        $rules = [
            'selectedExportType' => 'required|string|in:infoniqa-ma,infoniqa-dimensions,infoniqa-bank,infoniqa-zeitwirtschaft,infoniqa-zeitwirtschaft-monat,payroll,employees',
        ];

        // Für INFONIQA Exporte und Mitarbeiter-Export ist employer_id erforderlich
        if (in_array($this->selectedExportType, ['infoniqa-ma', 'infoniqa-dimensions', 'infoniqa-bank', 'infoniqa-zeitwirtschaft', 'infoniqa-zeitwirtschaft-monat', 'employees'], true)) {
            $rules['selectedEmployerId'] = 'required|exists:hcm_employers,id';
        }

        // Für Mitarbeiter-Export müssen Felder ausgewählt sein
        if ($this->selectedExportType === 'employees') {
            $rules['selectedEmployeeFields'] = 'required|array|min:1';
        }

        $this->validate($rules);

        // Modal sofort schließen
        $this->modalShow = false;

        // Werte zwischenspeichern für Export
        $exportType = $this->selectedExportType;
        $employerId = $this->selectedEmployerId;
        $employeeFields = $this->selectedEmployeeFields;

        // Formular zurücksetzen
        $this->selectedExportType = !empty($this->exportTypes) ? array_key_first($this->exportTypes) : '';
        $this->selectedEmployerId = null;
        $this->selectedEmployeeFields = [];

        try {
            $teamId = auth()->user()->currentTeam->id;
            $userId = auth()->id();

            $service = new HcmExportService($teamId, $userId);

            $name = match($exportType) {
                'infoniqa-ma' => 'INFONIQA MA Export',
                'infoniqa-dimensions' => 'INFONIQA Dimensionen Export',
                'infoniqa-bank' => 'INFONIQA Bank Export',
                'infoniqa-zeitwirtschaft' => 'Export Infoniqua Zeitwirtschaft',
                'infoniqa-zeitwirtschaft-monat' => 'Export Infoniqua Zeitwirtschaft (Laufender Monat)',
                'payroll' => 'Lohnarten Export',
                'employees' => 'Mitarbeiter Export',
                default => 'Export',
            };

            // Parameter für Exporte
            $parameters = null;
            if (in_array($exportType, ['infoniqa-ma', 'infoniqa-dimensions', 'infoniqa-bank', 'infoniqa-zeitwirtschaft', 'infoniqa-zeitwirtschaft-monat'], true)) {
                $parameters = ['employer_id' => $employerId];
            } elseif ($exportType === 'employees') {
                $parameters = [
                    'employer_id' => $employerId,
                    'fields' => $employeeFields,
                ];
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
        $this->selectedEmployeeFields = [];
    }

    public function downloadExport(int $exportId)
    {
        $export = HcmExport::findOrFail($exportId);
        
        // Team-Zugehörigkeit prüfen
        $teamId = auth()->user()->currentTeam?->id;
        if (!$teamId || $export->team_id !== $teamId) {
            session()->flash('error', 'Zugriff verweigert');
            return;
        }
        
        if (!$export->file_path) {
            session()->flash('error', 'Export-Datei nicht gefunden');
            return;
        }
        
        $disk = null;
        if (Storage::disk('local')->exists($export->file_path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($export->file_path)) {
            $disk = 'public';
        }

        if (!$disk) {
            session()->flash('error', 'Export-Datei nicht gefunden');
            return;
        }

        return Storage::disk($disk)->download($export->file_path, $export->file_name);
    }

    public function deleteExport(HcmExport $export): void
    {
        // Team-Zugehörigkeit prüfen
        $teamId = auth()->user()->currentTeam?->id;
        if (!$teamId || $export->team_id !== $teamId) {
            session()->flash('error', 'Zugriff verweigert');
            return;
        }
        
        if ($export->file_path) {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($export->file_path)) {
                    Storage::disk($disk)->delete($export->file_path);
                }
            }
        }
        
        $export->delete();
        
        // Alte Flash-Messages löschen und neue setzen
        session()->forget(['success', 'error']);
        session()->flash('success', 'Export gelöscht');
    }
    
    public function showErrorDetails(HcmExport $export): void
    {
        // Export-Modal schließen wenn Error-Modal geöffnet wird
        $this->modalShow = false;
        
        $this->errorDetails = $export->error_message;
        $this->showErrorModal = true;
    }
    
    public function updatedShowErrorModal($value): void
    {
        // Wenn Error-Modal geschlossen wird, sicherstellen dass Export-Modal auch geschlossen ist
        if (!$value) {
            $this->errorDetails = null;
        }
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
            'infoniqa-ma' => 'INFONIQA MA Export',
            'infoniqa-dimensions' => 'INFONIQA Dimensionen Export',
            'infoniqa-bank' => 'INFONIQA Bank Export',
            'infoniqa-zeitwirtschaft' => 'Export Infoniqua Zeitwirtschaft',
            'infoniqa-zeitwirtschaft-monat' => 'Export Infoniqua Zeitwirtschaft (Laufender Monat)',
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

    #[Computed]
    public function employeeExportFields()
    {
        return [
            'employee_number' => 'Personalnummer',
            'company_employee_number' => 'Personalnummer (Firma)',
            'last_name' => 'Nachname',
            'first_name' => 'Vorname',
            'phone' => 'Rufnummer (geschäftl. bevorzugt)',
            'email' => 'E-Mail (geschäftl. bevorzugt)',
            'primary_email' => 'Primäre E-Mail-Adresse',
            'employer' => 'Arbeitgeber',
            'status' => 'Status (Aktiv/Inaktiv)',
        ];
    }

    public function render()
    {
        return view('hcm::livewire.exports.index')
            ->layout('platform::layouts.app');
    }
}

