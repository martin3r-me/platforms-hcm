<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $tariffGroup->name }}" icon="heroicon-o-squares-2x2" />
    </x-slot>

    <x-ui-page-container>
        <div class="py-8">
            <div class="max-w-full sm:px-6 lg:px-8">
                <!-- Statistiken -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <x-ui-dashboard-tile
                        title="Tarifstufen"
                        :count="(int) $tariffGroup->tariffLevels->count()"
                        icon="bars-3"
                        variant="primary"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Tarifsätze"
                        :count="(int) $tariffGroup->tariffRates->count()"
                        icon="banknotes"
                        variant="success"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Tarifvertrag"
                        :count="(int) 1"
                        icon="document-text"
                        variant="secondary"
                        size="sm"
                    />
                    
                    <div class="p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="text-xs text-[var(--ui-muted)] mb-1">Status</div>
                        <div class="flex items-center gap-2">
                            @if($tariffGroup->tariffAgreement->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 mr-1')
                                    Aktiv
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    @svg('heroicon-o-x-circle', 'w-4 h-4 mr-1')
                                    Inaktiv
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Tariff Levels -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium text-gray-900">Tarifstufen</h2>
                        <span class="text-sm text-gray-500">{{ $tariffGroup->tariffLevels->count() }} Stufen</span>
                    </div>
                    <div class="bg-white shadow overflow-hidden sm:rounded-md">
                        <x-ui-table compact="true">
                            <x-ui-table-header>
                                <x-ui-table-header-cell compact="true">Stufe</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Progression</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                            </x-ui-table-header>
                            
                            <x-ui-table-body>
                                @foreach($tariffGroup->tariffLevels as $level)
                                    <x-ui-table-row compact="true">
                                        <x-ui-table-cell compact="true">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ $level->code }}
                                            </span>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            <div class="font-medium">{{ $level->name }}</div>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            @if($level->progression_months === 999)
                                                <span class="text-red-600 font-medium">Endstufe</span>
                                            @else
                                                <span class="text-sm text-gray-500">{{ $level->progression_months }} Monate</span>
                                            @endif
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            @if($level->progression_months === 999)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Endstufe
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Progression
                                                </span>
                                            @endif
                                        </x-ui-table-cell>
                                    </x-ui-table-row>
                                @endforeach
                            </x-ui-table-body>
                        </x-ui-table>
        </div>
    </div>

                <!-- Tariff Rates -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium text-gray-900">Tarifsätze</h2>
                        <span class="text-sm text-gray-500">{{ $tariffGroup->tariffRates->count() }} Sätze</span>
                    </div>
                    <div class="bg-white shadow overflow-hidden sm:rounded-md">
                        <x-ui-table compact="true">
                            <x-ui-table-header>
                                <x-ui-table-header-cell compact="true">Stufe</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Betrag</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Gültig von</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Gültig bis</x-ui-table-header-cell>
                            </x-ui-table-header>
                            
                            <x-ui-table-body>
                                @foreach($tariffGroup->tariffRates as $rate)
                                    <x-ui-table-row compact="true">
                                        <x-ui-table-cell compact="true">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ $rate->tariffLevel->code }}
                                            </span>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            <div class="font-medium text-green-600">{{ number_format((float)$rate->amount, 2, ',', '.') }} €</div>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            <div class="text-sm text-gray-500">{{ $rate->valid_from ? \Carbon\Carbon::parse($rate->valid_from)->format('d.m.Y') : 'N/A' }}</div>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            <div class="text-sm text-gray-500">{{ $rate->valid_until ? \Carbon\Carbon::parse($rate->valid_until)->format('d.m.Y') : 'Unbegrenzt' }}</div>
                                        </x-ui-table-cell>
                                    </x-ui-table-row>
                                @endforeach
                            </x-ui-table-body>
                        </x-ui-table>
                    </div>
                </div>

                <!-- Progression-Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-center gap-2 mb-4">
                        @svg('heroicon-o-arrow-trending-up', 'w-5 h-5 text-blue-600')
                        <h3 class="text-lg font-medium text-blue-900">Tarifstufen-Progression</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">
                                {{ $tariffGroup->tariffLevels->where('progression_months', '!=', 999)->count() }}
                            </div>
                            <div class="text-sm text-blue-700">Progression möglich</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">
                                {{ $tariffGroup->tariffLevels->where('progression_months', 999)->count() }}
                            </div>
                            <div class="text-sm text-green-700">Endstufen</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">
                                {{ number_format((float) ($tariffGroup->tariffLevels->where('progression_months', '!=', 999)->avg('progression_months') ?? 0), 1, ',', '.') }}
                            </div>
                            <div class="text-sm text-purple-700">Ø Progression (Monate)</div>
                        </div>
                    </div>
                </div>
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
                                Neue Tarifstufe
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                                Bearbeiten
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken (kompakt) --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $tariffGroup->tariffLevels->count() }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Stufen</div>
                        </div>
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                            <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $tariffGroup->tariffRates->count() }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Sätze</div>
                        </div>
                        <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center col-span-2">
                            <div class="text-xs text-[var(--ui-muted)]">Tarifvertrag</div>
                            <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $tariffGroup->tariffAgreement->name ?? 'N/A' }}</div>
                        </div>
                    </div>
                    @if(isset($this->progressionStats))
                        <div class="mt-4 pt-4 border-t border-[var(--ui-border)]">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $this->progressionStats['possible_progressions'] }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">Progressionen</div>
                                </div>
                                <div class="p-2 bg-[var(--ui-muted-5)] rounded-lg text-center">
                                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $this->progressionStats['final_levels'] }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">Endstufen</div>
                                </div>
                            </div>
                        </div>
                    @endif
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Tarifgruppe erstellt</div>
                        <div class="text-[var(--ui-muted)]">{{ $tariffGroup->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                    @if($tariffGroup->updated_at != $tariffGroup->created_at)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Zuletzt bearbeitet</div>
                            <div class="text-[var(--ui-muted)]">{{ $tariffGroup->updated_at->format('d.m.Y H:i') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
