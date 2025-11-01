<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $tariffLevel->name }}" icon="heroicon-o-bars-3" />
    </x-slot>

    <x-ui-page-container>
        <!-- Tariff Level Details -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Tarifstufe Details</h3>
            </div>
            <div class="px-6 py-4">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Code</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $tariffLevel->code }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $tariffLevel->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tarifgruppe</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="{{ route('hcm.tariff-groups.show', $tariffLevel->tariffGroup) }}" 
                               class="text-indigo-600 hover:text-indigo-900">
                                {{ $tariffLevel->tariffGroup->name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tarifvertrag</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="{{ route('hcm.tariff-agreements.show', $tariffLevel->tariffGroup->tariffAgreement) }}" 
                               class="text-indigo-600 hover:text-indigo-900">
                                {{ $tariffLevel->tariffGroup->tariffAgreement->name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Progression (Monate)</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($tariffLevel->progression_months)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $tariffLevel->progression_months }} Monate
                                </span>
                            @else
                                <span class="text-sm text-gray-400">Endstufe (keine Progression)</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Erstellt</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $tariffLevel->created_at->format('d.m.Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Tariff Rates -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Tarifsätze</h3>
            </div>
            <div class="overflow-x-auto">
                @if($tariffLevel->tariffRates->count() > 0)
                    <x-ui-table compact="true">
                        <x-ui-table-header>
                            <x-ui-table-header-cell compact="true">Gültig ab</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Gültig bis</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true" align="right">Betrag (€)</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                        </x-ui-table-header>
                        
                        <x-ui-table-body>
                            @foreach($tariffLevel->tariffRates as $rate)
                                <x-ui-table-row compact="true">
                                    <x-ui-table-cell compact="true">
                                        <div class="text-sm font-medium">{{ $rate->valid_from->format('d.m.Y') }}</div>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <div class="text-sm text-gray-500">
                                            {{ $rate->valid_until ? $rate->valid_until->format('d.m.Y') : 'Unbegrenzt' }}
                                        </div>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true" align="right">
                                        <div class="text-sm font-medium">{{ number_format((float)$rate->amount, 2, ',', '.') }} €</div>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        @if($rate->is_current)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Aktuell
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Historisch
                                            </span>
                                        @endif
                                    </x-ui-table-cell>
                                </x-ui-table-row>
                            @endforeach
                        </x-ui-table-body>
                    </x-ui-table>
                @else
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        Keine Tarifsätze für diese Stufe vorhanden
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
                                Neuer Tarifsatz
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

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifsätze</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $tariffLevel->tariffRates->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifgruppe</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $tariffLevel->tariffGroup->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Progression Info --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Progression</h3>
                    <div class="space-y-3">
                        @if($tariffLevel->progression_months && $tariffLevel->progression_months != 999)
                            <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                                <span class="text-sm text-[var(--ui-muted)]">Progression (Monate)</span>
                                <span class="font-semibold text-[var(--ui-secondary)]">{{ $tariffLevel->progression_months }}</span>
                            </div>
                        @else
                            <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                                <span class="text-sm text-[var(--ui-muted)]">Status</span>
                                <span class="font-semibold text-[var(--ui-secondary)]">Endstufe</span>
                            </div>
                        @endif
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Tarifstufe erstellt</div>
                        <div class="text-[var(--ui-muted)]">{{ $tariffLevel->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                    @if($tariffLevel->updated_at != $tariffLevel->created_at)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Zuletzt bearbeitet</div>
                            <div class="text-[var(--ui-muted)]">{{ $tariffLevel->updated_at->format('d.m.Y H:i') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>