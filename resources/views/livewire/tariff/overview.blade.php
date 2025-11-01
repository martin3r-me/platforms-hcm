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

            <!-- Tarif-Baum Struktur -->
            <div class="space-y-6">
                @foreach($this->tariffAgreements as $agreement)
                    <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 overflow-hidden">
                        <!-- Tarifvertrag Header -->
                        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-indigo-900/20 dark:to-blue-900/20 px-6 py-4 border-b border-[var(--ui-border)]/60">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @svg('heroicon-o-document-text', 'w-6 h-6 text-indigo-600')
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $agreement->name }}</h3>
                                        @if($agreement->description)
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $agreement->description }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                        {{ $agreement->code }}
                                    </span>
                                    <x-ui-badge variant="{{ $agreement->is_active ? 'success' : 'secondary' }}" size="sm">
                                        {{ $agreement->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                    <x-ui-button 
                                        size="sm" 
                                        variant="secondary" 
                                        href="{{ route('hcm.tariff-agreements.show', $agreement) }}" 
                                        wire:navigate
                                    >
                                        Details
                                    </x-ui-button>
                                </div>
                            </div>
                        </div>

                        <!-- Tarifgruppen -->
                        <div class="p-6">
                            @if($agreement->tariffGroups->count() > 0)
                                <div class="space-y-4">
                                    @foreach($agreement->tariffGroups as $group)
                                        <div class="border border-[var(--ui-border)]/40 rounded-lg overflow-hidden">
                                            <!-- Tarifgruppe Header -->
                                            <div class="bg-gray-50 dark:bg-gray-800/50 px-4 py-3 border-b border-[var(--ui-border)]/40">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        @svg('heroicon-o-squares-2x2', 'w-5 h-5 text-gray-600')
                                                        <div>
                                                            <h4 class="font-medium text-gray-900 dark:text-white">{{ $group->name }}</h4>
                                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $group->tariffLevels->count() }} Stufen</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                            {{ $group->code }}
                                                        </span>
                                                        <x-ui-button 
                                                            size="sm" 
                                                            variant="secondary-outline" 
                                                            href="{{ route('hcm.tariff-groups.show', $group) }}" 
                                                            wire:navigate
                                                        >
                                                            Details
                                                        </x-ui-button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Tarifstufen -->
                                            @if($group->tariffLevels->count() > 0)
                                                <div class="p-4">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                        @foreach($group->tariffLevels as $level)
                                                            <div class="border border-[var(--ui-border)]/40 rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                                                <div class="flex items-center justify-between mb-2">
                                                                    <div class="flex items-center gap-2">
                                                                        @svg('heroicon-o-bars-3', 'w-4 h-4 text-gray-500')
                                                                        <span class="font-medium text-gray-900 dark:text-white">{{ $level->name }}</span>
                                                                    </div>
                                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                        {{ $level->code }}
                                                                    </span>
                                                                </div>
                                                                
                                                                @if($level->progression_months && $level->progression_months != 999)
                                                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                                        Progression: {{ $level->progression_months }} Monate
                                                                    </div>
                                                                @else
                                                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                                        Endstufe
                                                                    </div>
                                                                @endif

                                                                <!-- Tarifsätze für diese Stufe -->
                                                                @if($level->tariffRates->count() > 0)
                                                                    <div class="space-y-1">
                                                                        @foreach($level->tariffRates->take(3) as $rate)
                                                                            <div class="flex items-center justify-between text-sm">
                                                                                <span class="font-medium text-green-600 dark:text-green-400">
                                                                                    {{ number_format((float)$rate->amount, 2, ',', '.') }} €
                                                                                </span>
                                                                                <x-ui-badge variant="{{ $rate->is_current ? 'success' : 'secondary' }}" size="xs">
                                                                                    {{ $rate->is_current ? 'Aktuell' : 'Historisch' }}
                                                                                </x-ui-badge>
                                                                            </div>
                                                                        @endforeach
                                                                        @if($level->tariffRates->count() > 3)
                                                                            <div class="text-xs text-gray-500">
                                                                                +{{ $level->tariffRates->count() - 3 }} weitere Sätze
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @else
                                                                    <div class="text-sm text-gray-500">Keine Tarifsätze</div>
                                                                @endif

                                                                <div class="mt-2">
                                                                    <x-ui-button 
                                                                        size="xs" 
                                                                        variant="secondary-outline" 
                                                                        href="{{ route('hcm.tariff-levels.show', $level) }}" 
                                                                        wire:navigate
                                                                        class="w-full"
                                                                    >
                                                                        Details
                                                                    </x-ui-button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @else
                                                <div class="p-4 text-center text-gray-500">
                                                    <div class="flex flex-col items-center">
                                                        @svg('heroicon-o-bars-3', 'w-8 h-8 text-gray-300 mb-2')
                                                        <p class="text-sm">Keine Tarifstufen</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500">
                                    <div class="flex flex-col items-center">
                                        @svg('heroicon-o-squares-2x2', 'w-12 h-12 text-gray-300 mb-4')
                                        <p class="text-lg font-medium">Keine Tarifgruppen</p>
                                        <p class="text-sm">Dieser Tarifvertrag hat noch keine Gruppen.</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if($this->tariffAgreements->count() === 0)
                    <div class="text-center py-12 text-gray-500">
                        <div class="flex flex-col items-center">
                            @svg('heroicon-o-document-text', 'w-16 h-16 text-gray-300 mb-4')
                            <p class="text-xl font-medium">Keine Tarifverträge</p>
                            <p class="text-sm">Erstellen Sie Ihren ersten Tarifvertrag um zu beginnen.</p>
                        </div>
                    </div>
                @endif
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
                    </div>
                </div>

                {{-- Statistiken (kompakt) --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['agreements'] }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Verträge</div>
                        </div>
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['groups'] }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Gruppen</div>
                        </div>
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['levels'] }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Stufen</div>
                        </div>
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ (int)$this->stats['rates'] }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Sätze</div>
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
