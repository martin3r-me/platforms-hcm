<?php

namespace Platform\Hcm\Livewire\HealthInsuranceCompany;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Platform\Hcm\Models\HcmHealthInsuranceCompany;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $editingCompany = null;

    // Form fields
    public $name = '';
    public $code = '';
    public $short_name = '';
    public $description = '';
    public $website = '';
    public $phone = '';
    public $email = '';
    public $address = '';
    public $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'code' => 'required|string|max:20|unique:hcm_health_insurance_companies,code',
        'short_name' => 'nullable|string|max:100',
        'description' => 'nullable|string',
        'website' => 'nullable|url',
        'phone' => 'nullable|string|max:50',
        'email' => 'nullable|email|max:255',
        'address' => 'nullable|string',
        'is_active' => 'boolean',
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(HcmHealthInsuranceCompany $company)
    {
        $this->editingCompany = $company;
        $this->name = $company->name;
        $this->code = $company->code;
        $this->short_name = $company->short_name;
        $this->description = $company->description;
        $this->website = $company->website;
        $this->phone = $company->phone;
        $this->email = $company->email;
        $this->address = $company->address;
        $this->is_active = $company->is_active;
        $this->showEditModal = true;
    }

    public function save()
    {
        if ($this->editingCompany) {
            $this->rules['code'] = 'required|string|max:20|unique:hcm_health_insurance_companies,code,' . $this->editingCompany->id;
        }

        $this->validate();

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'short_name' => $this->short_name,
            'description' => $this->description,
            'website' => $this->website,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'team_id' => auth()->user()->current_team_id,
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->editingCompany) {
            $this->editingCompany->update($data);
            session()->flash('success', 'Krankenkasse erfolgreich aktualisiert!');
        } else {
            HcmHealthInsuranceCompany::create($data);
            session()->flash('success', 'Krankenkasse erfolgreich erstellt!');
        }

        $this->closeModals();
        $this->resetForm();
    }

    public function delete(HcmHealthInsuranceCompany $company)
    {
        if ($company->employees()->count() > 0) {
            session()->flash('error', 'Krankenkasse kann nicht gelöscht werden, da sie noch Mitarbeitern zugeordnet ist!');
            return;
        }

        $company->delete();
        session()->flash('success', 'Krankenkasse erfolgreich gelöscht!');
    }

    public function closeModals()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->editingCompany = null;
    }

    public function resetForm()
    {
        $this->name = '';
        $this->code = '';
        $this->short_name = '';
        $this->description = '';
        $this->website = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->is_active = true;
        $this->editingCompany = null;
    }

    public function importStandardCompanies()
    {
        try {
            // Standard-Krankenkassen direkt erstellen
            $standardCompanies = [
                [
                    'name' => 'AOK - Die Gesundheitskasse',
                    'code' => 'AOK',
                    'short_name' => 'AOK',
                    'description' => 'Allgemeine Ortskrankenkasse',
                    'website' => 'https://www.aok.de',
                    'is_active' => true,
                ],
                [
                    'name' => 'Techniker Krankenkasse',
                    'code' => 'TK',
                    'short_name' => 'TK',
                    'description' => 'Techniker Krankenkasse',
                    'website' => 'https://www.tk.de',
                    'is_active' => true,
                ],
                [
                    'name' => 'Barmer',
                    'code' => 'BARMER',
                    'short_name' => 'Barmer',
                    'description' => 'Barmer Ersatzkasse',
                    'website' => 'https://www.barmer.de',
                    'is_active' => true,
                ],
                [
                    'name' => 'DAK-Gesundheit',
                    'code' => 'DAK',
                    'short_name' => 'DAK',
                    'description' => 'DAK-Gesundheit',
                    'website' => 'https://www.dak.de',
                    'is_active' => true,
                ],
                [
                    'name' => 'IKK classic',
                    'code' => 'IKK',
                    'short_name' => 'IKK classic',
                    'description' => 'IKK classic',
                    'website' => 'https://www.ikk-classic.de',
                    'is_active' => true,
                ],
            ];

            $imported = 0;
            foreach ($standardCompanies as $companyData) {
                // Prüfen ob bereits vorhanden
                $exists = HcmHealthInsuranceCompany::where('code', $companyData['code'])
                    ->where('team_id', auth()->user()->current_team_id)
                    ->exists();
                
                if (!$exists) {
                    HcmHealthInsuranceCompany::create([
                        ...$companyData,
                        'team_id' => auth()->user()->current_team_id,
                        'created_by_user_id' => auth()->id(),
                    ]);
                    $imported++;
                }
            }
            
            if ($imported > 0) {
                session()->flash('success', "{$imported} Standard-Krankenkassen erfolgreich importiert!");
            } else {
                session()->flash('info', 'Alle Standard-Krankenkassen sind bereits vorhanden.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Import: ' . $e->getMessage());
        }
    }

    #[Computed]
    public function companies()
    {
        return HcmHealthInsuranceCompany::query()
            ->where('team_id', auth()->user()->current_team_id)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('short_name', 'like', '%' . $this->search . '%');
                });
            })
            ->withCount('employees')
            ->orderBy('name')
            ->paginate(20);
    }

    public function render()
    {
        return view('hcm::livewire.health-insurance-company.index')
            ->layout('platform::layouts.app');
    }
}
