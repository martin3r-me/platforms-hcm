<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Arbeitgeber" icon="heroicon-o-building-office" />
    </x-slot>

    <x-ui-page-container>

        <!-- Haupt-Statistiken (4x1 Grid) -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <x-ui-dashboard-tile
                title="Gesamt"
                :count="$this->stats['total']"
                icon="building-office"
                variant="primary"
                size="lg"
            />
            
            <x-ui-dashboard-tile
                title="Aktiv"
                :count="$this->stats['active']"
                icon="check-circle"
                variant="success"
                size="lg"
            />
            
            <x-ui-dashboard-tile
                title="Inaktiv"
                :count="$this->stats['inactive']"
                icon="x-circle"
                variant="danger"
                size="lg"
            />
            
            <x-ui-dashboard-tile
                title="Mit Mitarbeitern"
                :count="$this->stats['with_employees']"
                icon="user-group"
                variant="secondary"
                size="lg"
            />
        </div>

        <!-- Arbeitgeber Tabelle -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wide">
                            <th class="px-6 py-3">
                                <button wire:click="sortBy('employer_number')" class="d-flex items-center gap-1 hover:text-gray-700">
                                    Arbeitgeber-Nr.
                                    @if($sortField === 'employer_number')
                                        @if($sortDirection === 'asc')
                                            @svg('heroicon-o-chevron-up', 'w-4 h-4')
                                        @else
                                            @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                        @endif
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-3">Unternehmen</th>
                            <th class="px-6 py-3">Mitarbeiter-Nummerierung</th>
                            <th class="px-6 py-3">Mitarbeiter</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Erstellt</th>
                            <th class="px-6 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse ($this->employers as $employer)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">
                                        {{ $employer->employer_number }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="d-flex items-center">
                                        @svg('heroicon-o-building-office', 'w-5 h-5 text-gray-400 mr-3')
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                {{ optional($employer->organizationCompanyLinks->first()?->company)->name ?? 'Kein Unternehmen verknüpft' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        @if($employer->employee_number_prefix)
                                            {{ $employer->employee_number_prefix }}0001 - {{ $employer->employee_number_prefix }}{{ str_pad($employer->employee_number_next - 1, 4, '0', STR_PAD_LEFT) }}
                                        @else
                                            0001 - {{ str_pad($employer->employee_number_next - 1, 4, '0', STR_PAD_LEFT) }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Nächste: {{ $employer->previewNextEmployeeNumber() }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        {{ $employer->employee_count }} Mitarbeiter
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $employer->active_employee_count }} aktiv
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($employer->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Aktiv
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inaktiv
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $employer->created_at->format('d.m.Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <x-ui-button 
                                        size="sm" 
                                        variant="secondary" 
                                        href="{{ route('hcm.employers.show', ['employer' => $employer->id]) }}" 
                                        wire:navigate
                                    >
                                        Bearbeiten
                                    </x-ui-button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        @svg('heroicon-o-building-office', 'w-12 h-12 text-gray-300 mb-4')
                                        <p class="text-lg font-medium">Keine Arbeitgeber gefunden</p>
                                        <p class="text-sm">Erstellen Sie Ihren ersten Arbeitgeber um zu beginnen.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Employer Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            Neuen Arbeitgeber erstellen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="createEmployer" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text
                        name="employer_number"
                        label="Arbeitgeber-Nummer"
                        wire:model.live="employer_number"
                        required
                        placeholder="z.B. EMP001"
                    />
                    
                    <x-ui-input-select
                        name="organization_entity_id"
                        label="Organisationseinheit"
                        :options="$this->availableCompanies"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="Organisationseinheit auswählen"
                        wire:model.live="organization_entity_id"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text
                        name="employee_number_prefix"
                        label="Mitarbeiter-Nummer Prefix (optional)"
                        wire:model.live="employee_number_prefix"
                        placeholder="z.B. EMP"
                    />
                    
                    <x-ui-input-text
                        name="employee_number_start"
                        label="Start-Nummer"
                        wire:model.live="employee_number_start"
                        type="number"
                        min="1"
                        required
                    />
                </div>

                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model.live="is_active" 
                        id="is_active"
                        class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                    />
                    <label for="is_active" class="ml-2 text-sm text-gray-700">Aktiv</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeCreateModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createEmployer">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
        
        {{-- weitere Inhalte oben ... --}}
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" wire:click="openCreateModal" class="w-full justify-start">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span class="ml-2">Neuer Arbeitgeber</span>
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 p-3 text-center">
                            <div class="text-lg font-bold text-[var(--ui-primary)]">{{ $this->stats['total'] ?? 0 }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Gesamt</div>
                        </div>
                        <div class="bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 p-3 text-center">
                            <div class="text-lg font-bold text-green-600">{{ $this->stats['active'] ?? 0 }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Aktiv</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-3 text-sm">
                        <div class="text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
