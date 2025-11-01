<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Krankenkassen" icon="heroicon-o-heart" />
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Übersicht" subtitle="Krankenkassen verwalten">
            <div class="flex justify-between items-center mb-4">
                <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" class="max-w-xs" />
                <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neu
                </x-ui-button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">IK-Nummer</th>
                            <th class="px-4 py-2">Kurzname</th>
                            <th class="px-4 py-2">Mitarbeiter</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->companies as $company)
                            <tr>
                                <td class="px-4 py-2">
                                    <div>
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $company->name }}</div>
                                        @if($company->short_name)
                                            <div class="text-xs text-[var(--ui-muted)]">{{ $company->short_name }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $company->code }}</td>
                                <td class="px-4 py-2">
                                    @if($company->ik_number)
                                        <span class="text-[var(--ui-muted)]">{{ $company->ik_number }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($company->short_name)
                                        <span class="text-[var(--ui-muted)]">{{ $company->short_name }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $company->employees_count }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <x-ui-badge variant="{{ $company->is_active ? 'success' : 'secondary' }}" size="xs">
                                        {{ $company->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <x-ui-button 
                                            size="xs" 
                                            variant="secondary-outline" 
                                            :href="route('hcm.health-insurance-companies.show', $company)"
                                            wire:navigate
                                        >
                                            Details
                                        </x-ui-button>
                                        <x-ui-button 
                                            size="xs" 
                                            variant="secondary-outline" 
                                            wire:click="openEditModal({{ $company->id }})"
                                        >
                                            Bearbeiten
                                        </x-ui-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center">
                                    @svg('heroicon-o-heart', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                    <div class="text-sm text-[var(--ui-muted)]">Keine Krankenkassen gefunden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="primary" size="sm" wire:click="openCreateModal" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neue Krankenkasse
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="importStandardCompanies" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                                Alle importieren
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Gesamt</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->companies->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Aktiv</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->companies->where('is_active', true)->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Inaktiv</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->companies->where('is_active', false)->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Krankenkassen-Übersicht geladen</div>
                        <div class="text-[var(--ui-muted)]">{{ now()->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Create Modal --}}
    <x-ui-modal wire:model="showCreateModal" title="Neue Krankenkasse">
        <div class="space-y-4">
            <x-ui-input-text 
                name="name"
                wire:model="name" 
                label="Name *"
                placeholder="Vollständiger Name der Krankenkasse"
            />
            
            <x-ui-input-text 
                name="code"
                wire:model="code" 
                label="Code *"
                placeholder="Eindeutiger Code"
            />
            
            <x-ui-input-text 
                name="ik_number"
                wire:model="ik_number" 
                label="IK-Nummer"
                placeholder="Institutionskennzeichen (optional)"
            />
            
            <x-ui-input-text 
                name="short_name"
                wire:model="short_name" 
                label="Kurzname"
                placeholder="Kurzer Name (optional)"
            />
            
            <x-ui-input-textarea 
                name="description"
                wire:model="description" 
                label="Beschreibung"
                placeholder="Beschreibung der Krankenkasse"
                rows="3"
            />
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-text 
                    name="website"
                    wire:model="website" 
                    label="Website"
                    placeholder="https://..."
                />
                
                <x-ui-input-text 
                    name="phone"
                    wire:model="phone" 
                    label="Telefon"
                    placeholder="+49 ..."
                />
            </div>
            
            <x-ui-input-text 
                name="email"
                wire:model="email" 
                label="E-Mail"
                placeholder="kontakt@krankenkasse.de"
            />
            
            <x-ui-input-textarea 
                name="address"
                wire:model="address" 
                label="Adresse"
                placeholder="Vollständige Adresse"
                rows="3"
            />
            
            <x-ui-input-checkbox 
                name="is_active"
                wire:model="is_active" 
                label="Aktiv"
                :model="'is_active'"
            />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-ui-button variant="secondary" wire:click="closeModals">
                    Abbrechen
                </x-ui-button>
                <x-ui-button variant="primary" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    Erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Edit Modal --}}
    <x-ui-modal wire:model="showEditModal" title="Krankenkasse bearbeiten">
        <div class="space-y-4">
            <x-ui-input-text 
                name="name"
                wire:model="name" 
                label="Name *"
                placeholder="Vollständiger Name der Krankenkasse"
            />
            
            <x-ui-input-text 
                name="code"
                wire:model="code" 
                label="Code *"
                placeholder="Eindeutiger Code"
            />
            
            <x-ui-input-text 
                name="ik_number"
                wire:model="ik_number" 
                label="IK-Nummer"
                placeholder="Institutionskennzeichen (optional)"
            />
            
            <x-ui-input-text 
                name="short_name"
                wire:model="short_name" 
                label="Kurzname"
                placeholder="Kurzer Name (optional)"
            />
            
            <x-ui-input-textarea 
                name="description"
                wire:model="description" 
                label="Beschreibung"
                placeholder="Beschreibung der Krankenkasse"
                rows="3"
            />
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-text 
                    name="website"
                    wire:model="website" 
                    label="Website"
                    placeholder="https://..."
                />
                
                <x-ui-input-text 
                    name="phone"
                    wire:model="phone" 
                    label="Telefon"
                    placeholder="+49 ..."
                />
            </div>
            
            <x-ui-input-text 
                name="email"
                wire:model="email" 
                label="E-Mail"
                placeholder="kontakt@krankenkasse.de"
            />
            
            <x-ui-input-textarea 
                name="address"
                wire:model="address" 
                label="Adresse"
                placeholder="Vollständige Adresse"
                rows="3"
            />
            
            <x-ui-input-checkbox 
                name="is_active"
                wire:model="is_active" 
                label="Aktiv"
                :model="'is_active'"
            />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-ui-button variant="secondary" wire:click="closeModals">
                    Abbrechen
                </x-ui-button>
                <x-ui-button variant="primary" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    Speichern
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
