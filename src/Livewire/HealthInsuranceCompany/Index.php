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
        public $ik_number = '';
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
            'ik_number' => 'nullable|string|max:20',
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
            $this->ik_number = $company->ik_number;
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
                'ik_number' => $this->ik_number,
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
            $this->ik_number = '';
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
            // Vollständige Liste der deutschen Krankenkassen mit IK-Nummern
            $healthInsuranceCompanies = [
                ['name' => 'AOK - Die Gesundheitskasse für Niedersachsen', 'code' => 'AOK-NDS', 'ik_number' => '29720865'],
                ['name' => 'AOK Baden-Württemberg Hauptverwaltung', 'code' => 'AOK-BW', 'ik_number' => '67450665'],
                ['name' => 'AOK Bayern Die Gesundheitskasse', 'code' => 'AOK-BY', 'ik_number' => '87880235'],
                ['name' => 'AOK Bremen/Bremerhaven', 'code' => 'AOK-HB', 'ik_number' => '20012084'],
                ['name' => 'AOK Hessen Direktion', 'code' => 'AOK-HE', 'ik_number' => '45118687'],
                ['name' => 'AOK Nordost - Die Gesundheitskasse', 'code' => 'AOK-NO', 'ik_number' => '90235319'],
                ['name' => 'AOK NordWest', 'code' => 'AOK-NW', 'ik_number' => '33526082'],
                ['name' => 'AOK PLUS Die Gesundheitskasse', 'code' => 'AOK-PLUS', 'ik_number' => '5174740'],
                ['name' => 'AOK Rheinland/Hamburg Die Gesundheitskasse', 'code' => 'AOK-RH', 'ik_number' => '34364249'],
                ['name' => 'AOK Rheinland-Pfalz/Saarland', 'code' => 'AOK-RP', 'ik_number' => '51605725'],
                ['name' => 'AOK Sachsen-Anhalt', 'code' => 'AOK-SA', 'ik_number' => '1029141'],
                ['name' => 'BARMER (vormals BARMER GEK)', 'code' => 'BARMER', 'ik_number' => '42938966'],
                ['name' => 'DAK-Gesundheit', 'code' => 'DAK', 'ik_number' => '48698890'],
                ['name' => 'Techniker Krankenkasse -Rechtskreis West und Ost-', 'code' => 'TK', 'ik_number' => '15027365'],
                ['name' => 'IKK classic', 'code' => 'IKK', 'ik_number' => '1049203'],
                ['name' => 'hkk Handelskrankenkasse', 'code' => 'HKK', 'ik_number' => '20013461'],
                ['name' => 'HEK Hanseatische Krankenkasse', 'code' => 'HEK', 'ik_number' => '15031806'],
                ['name' => 'KKH Kaufmännische Krankenkasse', 'code' => 'KKH', 'ik_number' => '29137937'],
                ['name' => 'BIG direkt gesund', 'code' => 'BIG', 'ik_number' => '97141402'],
                ['name' => 'pronova BKK', 'code' => 'PRONOVA', 'ik_number' => '15872672'],
            ];

            $imported = 0;
            foreach ($healthInsuranceCompanies as $companyData) {
                // Prüfen ob bereits vorhanden
                $exists = HcmHealthInsuranceCompany::where('code', $companyData['code'])
                    ->where('team_id', auth()->user()->current_team_id)
                    ->exists();
                
                if (!$exists) {
                    HcmHealthInsuranceCompany::create([
                        'name' => $companyData['name'],
                        'code' => $companyData['code'],
                        'ik_number' => $companyData['ik_number'],
                        'is_active' => true,
                        'team_id' => auth()->user()->current_team_id,
                        'created_by_user_id' => auth()->id(),
                    ]);
                    $imported++;
                }
            }
            
            if ($imported > 0) {
                session()->flash('success', "{$imported} Krankenkassen erfolgreich importiert!");
            } else {
                session()->flash('info', 'Alle Krankenkassen sind bereits vorhanden.');
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
