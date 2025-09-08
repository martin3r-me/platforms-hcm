<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $employer->display_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Arbeitgeber-Nr.: {{ $employer->employer_number }}</p>
            </div>
            <div class="d-flex items-center gap-2">
                <x-ui-button variant="secondary" wire:click="$set('settingsModalShow', true)">
                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 mr-2')
                    Einstellungen
                </x-ui-button>
                <x-ui-button variant="primary" href="{{ route('hcm.employers.employees.index', ['employer' => $employer->id]) }}" wire:navigate>
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Mitarbeiter hinzufügen
                </x-ui-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full sm:px-6 lg:px-8">
            <!-- Statistiken -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <x-ui-dashboard-tile
                    title="Mitarbeiter Gesamt"
                    :count="$this->stats['total_employees']"
                    icon="user-group"
                    variant="primary"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Aktive Mitarbeiter"
                    :count="$this->stats['active_employees']"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Nächste Nummer"
                    :count="$this->stats['next_employee_number']"
                    icon="hashtag"
                    variant="secondary"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Letzte Nummer"
                    :count="$this->stats['last_employee_number']"
                    icon="document-text"
                    variant="info"
                    size="sm"
                />
            </div>

            <!-- Arbeitgeber Details -->
            <div class="bg-white shadow sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Arbeitgeber Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Grunddaten</h4>
                            <dl class="space-y-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Arbeitgeber-Nummer</dt>
                                    <dd class="text-sm text-gray-900">{{ $employer->employer_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($employer->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Aktiv
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Inaktiv
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Erstellt am</dt>
                                    <dd class="text-sm text-gray-900">{{ $employer->created_at->format('d.m.Y H:i') }}</dd>
                                </div>
                            </dl>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Mitarbeiter-Nummerierung</h4>
                            <dl class="space-y-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Prefix</dt>
                                    <dd class="text-sm text-gray-900">{{ $employer->employee_number_prefix ?: 'Kein Prefix' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Start-Nummer</dt>
                                    <dd class="text-sm text-gray-900">{{ $employer->employee_number_start }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nächste Nummer</dt>
                                    <dd class="text-sm text-gray-900">{{ $employer->previewNextEmployeeNumber() }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Beispiel</dt>
                                    <dd class="text-sm text-gray-900">{{ $employer->previewNextEmployeeNumber() }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verknüpfte Organisationseinheit -->
            @if(optional($employer->organizationCompanyLinks->first()?->company))
            <div class="bg-white shadow sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Verknüpfte Organisationseinheit</h3>
                    
                    <div class="d-flex items-center">
                        @svg('heroicon-o-building-office', 'w-8 h-8 text-gray-400 mr-4')
                        <div>
                            <h4 class="font-medium text-gray-900">{{ optional($employer->organizationCompanyLinks->first()?->company)->name }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Mitarbeiter Liste -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="d-flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Mitarbeiter ({{ $this->employees->count() }})</h3>
                        <x-ui-button variant="primary" href="{{ route('hcm.employers.employees.index', ['employer' => $employer->id]) }}" wire:navigate>
                            @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                            Mitarbeiter hinzufügen
                        </x-ui-button>
                    </div>
                    
                    @if($this->employees->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto border-collapse text-sm">
                                <thead class="bg-gray-50">
                                    <tr class="text-left text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wide">
                                        <th class="px-6 py-3">Mitarbeiter-Nr.</th>
                                        <th class="px-6 py-3">Name</th>
                                        <th class="px-6 py-3">Kontakt</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3 text-right">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($this->employees as $employee)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900">
                                                    {{ $employee->employee_number }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900">
                                                    {{ $employee->full_name ?? 'Kein Name' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                @if($employee->crmContactLinks->count() > 0)
                                                    <div class="text-sm text-gray-900">
                                                        @if($employee->crmContactLinks->first()->contact->emailAddresses->where('is_primary', true)->first())
                                                            {{ $employee->crmContactLinks->first()->contact->emailAddresses->where('is_primary', true)->first()->email_address }}
                                                        @else
                                                            <span class="text-gray-400">Keine E-Mail</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">Kein Kontakt</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4">
                                                @if($employee->is_active)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Aktiv
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        Inaktiv
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <x-ui-button 
                                                    size="sm" 
                                                    variant="secondary" 
                                                    href="{{ route('hcm.employees.show', ['employee' => $employee->id]) }}" 
                                                    wire:navigate
                                                >
                                                    Bearbeiten
                                                </x-ui-button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            @svg('heroicon-o-user-group', 'w-12 h-12 text-gray-300 mx-auto mb-4')
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Keine Mitarbeiter</h4>
                            <p class="text-gray-600">Fügen Sie den ersten Mitarbeiter hinzu.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <x-ui-modal
        wire:model="settingsModalShow"
        size="lg"
    >
        <x-slot name="header">
            Arbeitgeber Einstellungen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="saveSettings" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text
                        name="employee_number_prefix"
                        label="Mitarbeiter-Nummer Prefix"
                        wire:model.live="settingsForm.employee_number_prefix"
                        placeholder="z.B. EMP (optional)"
                    />
                    
                    <x-ui-input-text
                        name="employee_number_start"
                        label="Start-Nummer"
                        wire:model.live="settingsForm.employee_number_start"
                        type="number"
                        min="1"
                        required
                    />
                </div>

                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model.live="settingsForm.is_active" 
                        id="settings_is_active"
                        class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                    />
                    <label for="settings_is_active" class="ml-2 text-sm text-gray-700">Aktiv</label>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="d-flex items-center gap-2 mb-2">
                        @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-yellow-600')
                        <h4 class="font-medium text-yellow-900">Vorsicht</h4>
                    </div>
                    <p class="text-yellow-700 text-sm">Das Zurücksetzen der Mitarbeiter-Nummerierung kann zu Duplikaten führen, wenn bereits Mitarbeiter mit höheren Nummern existieren.</p>
                </div>

                <div class="d-flex items-center gap-4">
                    <x-ui-button 
                        type="button" 
                        variant="danger-outline" 
                        wire:click="resetEmployeeNumbering"
                        wire:confirm="Sind Sie sicher, dass Sie die Mitarbeiter-Nummerierung zurücksetzen möchten?"
                    >
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 mr-2')
                        Nummerierung zurücksetzen
                    </x-ui-button>
                    
                    <span class="text-sm text-gray-500">
                        Nächste Nummer: {{ $employer->previewNextEmployeeNumber() }}
                    </span>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="$set('settingsModalShow', false)"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveSettings">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    Speichern
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</div>
