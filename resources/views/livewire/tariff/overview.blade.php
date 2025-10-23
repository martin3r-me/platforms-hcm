<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Tarif-Übersicht" icon="heroicon-o-scale" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <!-- Statistiken -->
            <div class="grid grid-cols-4 gap-4 mb-8">
                <x-ui-dashboard-tile
                    title="Tarifverträge"
                    :count="(int)$this->stats['agreements']"
                    icon="document-text"
                    variant="primary"
                    size="lg"
                />
                
                <x-ui-dashboard-tile
                    title="Tarifgruppen"
                    :count="(int)$this->stats['groups']"
                    icon="squares-2x2"
                    variant="success"
                    size="lg"
                />
                
                <x-ui-dashboard-tile
                    title="Tarifstufen"
                    :count="(int)$this->stats['levels']"
                    icon="bars-3"
                    variant="warning"
                    size="lg"
                />
                
                <x-ui-dashboard-tile
                    title="Tarifsätze"
                    :count="(int)$this->stats['rates']"
                    icon="banknotes"
                    variant="secondary"
                    size="lg"
                />
            </div>

            <!-- Tarifverträge Tabelle -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tarifverträge</h2>
                <x-ui-table compact="true">
                    <x-ui-table-header>
                        <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Code</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Gruppen</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
                    </x-ui-table-header>
                    
                    <x-ui-table-body>
                        @foreach($this->tariffAgreements as $agreement)
                            <x-ui-table-row 
                                compact="true"
                                clickable="true" 
                                :href="route('hcm.tariff-agreements.show', $agreement)"
                            >
                                <x-ui-table-cell compact="true">
                                    <div class="font-medium">{{ $agreement->name }}</div>
                                    @if($agreement->description)
                                        <div class="text-xs text-muted">{{ Str::limit($agreement->description, 50) }}</div>
                                    @endif
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                        {{ $agreement->code }}
                                    </span>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <span class="text-sm">{{ $agreement->tariffGroups->count() }} Gruppen</span>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <x-ui-badge variant="{{ $agreement->is_active ? 'success' : 'secondary' }}" size="sm">
                                        {{ $agreement->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true" align="right">
                                    <x-ui-button 
                                        size="sm" 
                                        variant="secondary" 
                                        href="{{ route('hcm.tariff-agreements.show', $agreement) }}" 
                                        wire:navigate
                                    >
                                        Anzeigen
                                    </x-ui-button>
                                </x-ui-table-cell>
                            </x-ui-table-row>
                        @endforeach
                    </x-ui-table-body>
                </x-ui-table>
            </div>

            <!-- Tarifgruppen Tabelle -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tarifgruppen</h2>
                <x-ui-table compact="true">
                    <x-ui-table-header>
                        <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Code</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Tarifvertrag</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Stufen</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
                    </x-ui-table-header>
                    
                    <x-ui-table-body>
                        @foreach($this->tariffGroups as $group)
                            <x-ui-table-row 
                                compact="true"
                                clickable="true" 
                                :href="route('hcm.tariff-groups.show', $group)"
                            >
                                <x-ui-table-cell compact="true">
                                    <div class="font-medium">{{ $group->name }}</div>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                        {{ $group->code }}
                                    </span>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <div class="text-sm">{{ $group->tariffAgreement->name ?? 'N/A' }}</div>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <span class="text-sm">{{ $group->tariffLevels->count() }} Stufen</span>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true" align="right">
                                    <x-ui-button 
                                        size="sm" 
                                        variant="secondary" 
                                        href="{{ route('hcm.tariff-groups.show', $group) }}" 
                                        wire:navigate
                                    >
                                        Anzeigen
                                    </x-ui-button>
                                </x-ui-table-cell>
                            </x-ui-table-row>
                        @endforeach
                    </x-ui-table-body>
                </x-ui-table>
            </div>

            <!-- Tarifstufen Tabelle -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tarifstufen</h2>
                <x-ui-table compact="true">
                    <x-ui-table-header>
                        <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Code</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Tarifgruppe</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Progression</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
                    </x-ui-table-header>
                    
                    <x-ui-table-body>
                        @foreach($this->tariffLevels as $level)
                            <x-ui-table-row 
                                compact="true"
                                clickable="true" 
                                :href="route('hcm.tariff-levels.show', $level)"
                            >
                                <x-ui-table-cell compact="true">
                                    <div class="font-medium">{{ $level->name }}</div>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                        {{ $level->code }}
                                    </span>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <div class="text-sm">{{ $level->tariffGroup->name ?? 'N/A' }}</div>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    @if($level->progression_months && $level->progression_months != 999)
                                        <span class="text-sm">{{ $level->progression_months }} Monate</span>
                                    @else
                                        <span class="text-sm text-muted">Endstufe</span>
                                    @endif
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true" align="right">
                                    <x-ui-button 
                                        size="sm" 
                                        variant="secondary" 
                                        href="{{ route('hcm.tariff-levels.show', $level) }}" 
                                        wire:navigate
                                    >
                                        Anzeigen
                                    </x-ui-button>
                                </x-ui-table-cell>
                            </x-ui-table-row>
                        @endforeach
                    </x-ui-table-body>
                </x-ui-table>
            </div>

            <!-- Tarifsätze Tabelle -->
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tarifsätze</h2>
                <x-ui-table compact="true">
                    <x-ui-table-header>
                        <x-ui-table-header-cell compact="true">Betrag</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Tarifstufe</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Tarifgruppe</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Gültig ab</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
                    </x-ui-table-header>
                    
                    <x-ui-table-body>
                        @foreach($this->tariffRates as $rate)
                            <x-ui-table-row 
                                compact="true"
                                clickable="true" 
                                :href="route('hcm.tariff-rates.show', $rate)"
                            >
                                <x-ui-table-cell compact="true">
                                    <div class="font-medium text-green-600 dark:text-green-400">
                                        {{ number_format((float)$rate->amount, 2, ',', '.') }} €
                                    </div>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                        {{ $rate->tariffLevel->code }}
                                    </span>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <div class="text-sm">{{ $rate->tariffLevel->tariffGroup->name ?? 'N/A' }}</div>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <div class="text-sm">{{ $rate->valid_from->format('d.m.Y') }}</div>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    <x-ui-badge variant="{{ $rate->is_current ? 'success' : 'secondary' }}" size="sm">
                                        {{ $rate->is_current ? 'Aktuell' : 'Historisch' }}
                                    </x-ui-badge>
                                </x-ui-table-cell>
                                <x-ui-table-cell compact="true" align="right">
                                    <x-ui-button 
                                        size="sm" 
                                        variant="secondary" 
                                        href="{{ route('hcm.tariff-rates.show', $rate) }}" 
                                        wire:navigate
                                    >
                                        Anzeigen
                                    </x-ui-button>
                                </x-ui-table-cell>
                            </x-ui-table-row>
                        @endforeach
                    </x-ui-table-body>
                </x-ui-table>
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="primary" size="sm" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neuer Tarifvertrag
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-document-arrow-down', 'w-4 h-4')
                                Import
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifverträge</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['agreements'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifgruppen</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['groups'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifstufen</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['levels'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifsätze</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['rates'] }}</span>
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Tarif-Übersicht geladen</div>
                        <div class="text-[var(--ui-muted)]">{{ now()->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
