<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Krankenkassen" icon="heroicon-o-heart" />
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        {{-- Search --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-6">
            <x-ui-input-text 
                name="search"
                wire:model.live.debounce.300ms="search" 
                placeholder="Krankenkasse suchen..."
                icon="heroicon-o-magnifying-glass"
            />
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60">
            <x-ui-table>
                <x-slot name="header">
                    <x-ui-table-header>Name</x-ui-table-header>
                    <x-ui-table-header>Code</x-ui-table-header>
                    <x-ui-table-header>IK-Nummer</x-ui-table-header>
                    <x-ui-table-header>Kurzname</x-ui-table-header>
                    <x-ui-table-header>Mitarbeiter</x-ui-table-header>
                    <x-ui-table-header>Status</x-ui-table-header>
                    <x-ui-table-header class="text-right">Aktionen</x-ui-table-header>
                </x-slot>

                @forelse($this->companies as $company)
                    <x-ui-table-row>
                        <x-ui-table-cell>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                                    @svg('heroicon-o-heart', 'w-5 h-5')
                                </div>
                                <div>
                                    <div class="font-medium text-[var(--ui-secondary)]">{{ $company->name }}</div>
                                    @if($company->short_name)
                                        <div class="text-sm text-[var(--ui-muted)]">{{ $company->short_name }}</div>
                                    @endif
                                </div>
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ $company->code }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="text-sm text-[var(--ui-muted)]">
                                {{ $company->ik_number ?: '-' }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="text-sm text-[var(--ui-muted)]">
                                {{ $company->short_name ?: '-' }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">
                                {{ $company->employees_count }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                @if($company->is_active) bg-green-100 text-green-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ $company->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui-button 
                                    size="sm" 
                                    variant="secondary-outline" 
                                    :href="route('hcm.health-insurance-companies.show', $company)"
                                    wire:navigate
                                >
                                    @svg('heroicon-o-eye', 'w-4 h-4')
                                </x-ui-button>
                                <x-ui-button 
                                    size="sm" 
                                    variant="secondary-outline" 
                                    wire:click="openEditModal({{ $company->id }})"
                                >
                                    @svg('heroicon-o-pencil', 'w-4 h-4')
                                </x-ui-button>
                                <x-ui-button 
                                    size="sm" 
                                    variant="danger-outline" 
                                    wire:click="delete({{ $company->id }})"
                                    wire:confirm="Sind Sie sicher, dass Sie diese Krankenkasse löschen möchten?"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </x-ui-button>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row>
                        <x-ui-table-cell colspan="7" class="text-center py-12">
                            <div class="text-[var(--ui-muted)]">
                                @svg('heroicon-o-heart', 'w-12 h-12 mx-auto mb-4 text-[var(--ui-muted)]')
                                <p class="text-lg font-medium mb-2">Keine Krankenkassen gefunden</p>
                                <p class="text-sm">Erstellen Sie Ihre erste Krankenkasse oder passen Sie Ihre Suche an.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table>

            @if($this->companies->hasPages())
                <div class="px-6 py-4 border-t border-[var(--ui-border)]">
                    {{ $this->companies->links() }}
                </div>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Create Modal --}}
    <x-ui-modal wire:model="showCreateModal" title="Neue Krankenkasse">
        <div class="space-y-6">
            <x-ui-input-text 
                name="name"
                wire:model.live="name" 
                label="Name *"
                placeholder="Vollständiger Name der Krankenkasse"
            />
            
            <x-ui-input-text 
                name="code"
                wire:model.live="code" 
                label="Code *"
                placeholder="Eindeutiger Code"
            />
            
            <x-ui-input-text 
                name="ik_number"
                wire:model.live="ik_number" 
                label="IK-Nummer"
                placeholder="Institutionskennzeichen (optional)"
            />
            
            <x-ui-input-text 
                name="short_name"
                wire:model.live="short_name" 
                label="Kurzname"
                placeholder="Kurzer Name (optional)"
            />
            
            <x-ui-input-textarea 
                name="description"
                wire:model.live="description" 
                label="Beschreibung"
                placeholder="Beschreibung der Krankenkasse"
                rows="3"
            />
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-text 
                    name="website"
                    wire:model.live="website" 
                    label="Website"
                    placeholder="https://..."
                />
                
                <x-ui-input-text 
                    name="phone"
                    wire:model.live="phone" 
                    label="Telefon"
                    placeholder="+49 ..."
                />
            </div>
            
            <x-ui-input-text 
                name="email"
                wire:model.live="email" 
                label="E-Mail"
                placeholder="kontakt@krankenkasse.de"
            />
            
            <x-ui-input-textarea 
                name="address"
                wire:model.live="address" 
                label="Adresse"
                placeholder="Vollständige Adresse"
                rows="3"
            />
            
            <x-ui-input-checkbox 
                name="is_active"
                wire:model.live="is_active" 
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
        <div class="space-y-6">
            <x-ui-input-text 
                name="name"
                wire:model.live="name" 
                label="Name *"
                placeholder="Vollständiger Name der Krankenkasse"
            />
            
            <x-ui-input-text 
                name="code"
                wire:model.live="code" 
                label="Code *"
                placeholder="Eindeutiger Code"
            />
            
            <x-ui-input-text 
                name="ik_number"
                wire:model.live="ik_number" 
                label="IK-Nummer"
                placeholder="Institutionskennzeichen (optional)"
            />
            
            <x-ui-input-text 
                name="short_name"
                wire:model.live="short_name" 
                label="Kurzname"
                placeholder="Kurzer Name (optional)"
            />
            
            <x-ui-input-textarea 
                name="description"
                wire:model.live="description" 
                label="Beschreibung"
                placeholder="Beschreibung der Krankenkasse"
                rows="3"
            />
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-text 
                    name="website"
                    wire:model.live="website" 
                    label="Website"
                    placeholder="https://..."
                />
                
                <x-ui-input-text 
                    name="phone"
                    wire:model.live="phone" 
                    label="Telefon"
                    placeholder="+49 ..."
                />
            </div>
            
            <x-ui-input-text 
                name="email"
                wire:model.live="email" 
                label="E-Mail"
                placeholder="kontakt@krankenkasse.de"
            />
            
            <x-ui-input-textarea 
                name="address"
                wire:model.live="address" 
                label="Adresse"
                placeholder="Vollständige Adresse"
                rows="3"
            />
            
            <x-ui-input-checkbox 
                name="is_active"
                wire:model.live="is_active" 
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

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Aktionen" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" wire:click="importStandardCompanies" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                                Alle Krankenkassen importieren
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="primary" size="sm" wire:click="openCreateModal" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neue Krankenkasse
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <span class="text-sm text-[var(--ui-secondary)]">Gesamt</span>
                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $this->companies->total() }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <span class="text-sm text-[var(--ui-secondary)]">Aktiv</span>
                            <span class="text-sm font-semibold text-[var(--ui-success)]">{{ $this->companies->where('is_active', true)->count() }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <span class="text-sm text-[var(--ui-secondary)]">Inaktiv</span>
                            <span class="text-sm font-semibold text-[var(--ui-muted)]">{{ $this->companies->where('is_active', false)->count() }}</span>
                        </div>
                    </div>
                </div>

                {{-- Quick Links --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Navigation</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('hcm.dashboard')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-home', 'w-4 h-4')
                                HCM Dashboard
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('hcm.employees.index')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-user-group', 'w-4 h-4')
                                Mitarbeiter
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('hcm.tariff-overview')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-scale', 'w-4 h-4')
                                Tarif-Übersicht
                            </span>
                        </x-ui-button>
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
                    @if($this->companies->count() > 0)
                        @foreach($this->companies->take(5) as $company)
                            <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $company->name }}</div>
                                <div class="text-[var(--ui-muted)]">{{ $company->created_at->format('d.m.Y H:i') }}</div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-sm text-[var(--ui-muted)]">Keine Aktivitäten</div>
                    @endif
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>