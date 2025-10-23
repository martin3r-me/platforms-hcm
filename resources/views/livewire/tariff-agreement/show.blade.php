<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $tariffAgreement->name }}" icon="heroicon-o-document-text">
            <div class="flex items-center gap-2">
                <a href="{{ route('hcm.tariff-agreements.index') }}" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]" wire:navigate>
                    ← Tarifverträge
                </a>
                @if($tariffAgreement->is_active)
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
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <div class="py-8">
            <div class="max-w-full sm:px-6 lg:px-8">
                <!-- Statistiken -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <x-ui-dashboard-tile
                        title="Tarifgruppen"
                        :count="(int) $tariffAgreement->tariffGroups->count()"
                        icon="squares-2x2"
                        variant="primary"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Tarifstufen"
                        :count="(int) $tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffLevels->count(); })"
                        icon="bars-3"
                        variant="success"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Tarifsätze"
                        :count="(int) $tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffRates->count(); })"
                        icon="banknotes"
                        variant="secondary"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Status"
                        :count="(string) ($tariffAgreement->is_active ? 'Aktiv' : 'Inaktiv')"
                        icon="check-circle"
                        variant="info"
                        size="sm"
                    />
                </div>

                <!-- Tariff Groups -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium text-gray-900">Tarifgruppen</h2>
                        <span class="text-sm text-gray-500">{{ $tariffAgreement->tariffGroups->count() }} Gruppen</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($tariffAgreement->tariffGroups as $group)
                            <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-sm font-medium text-gray-900">{{ $group->name }}</h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $group->code }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p>{{ $group->tariffLevels->count() }} Stufen</p>
                                    <p>{{ $group->tariffRates->count() }} Tarifsätze</p>
                                </div>
                                <div class="mt-3">
                                    <a href="{{ route('hcm.tariff-groups.show', $group) }}" 
                                       class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                        Details anzeigen →
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Tarifvertrag Details -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tarifvertrag Details</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Code</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffAgreement->code }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @if($tariffAgreement->is_active)
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
                            <dt class="text-sm font-medium text-gray-500">Team</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffAgreement->team->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Erstellt</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffAgreement->created_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    </dl>
                    
                    @if($tariffAgreement->description)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Beschreibung</h4>
                            <p class="text-sm text-gray-600">{{ $tariffAgreement->description }}</p>
                        </div>
                    @endif
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
                                Neue Tarifgruppe
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
                            <span class="text-sm text-[var(--ui-muted)]">Tarifgruppen</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $tariffAgreement->tariffGroups->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifstufen</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffLevels->count(); }) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Tarifsätze</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffRates->count(); }) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Status</h3>
                    <div class="p-3 rounded-lg {{ $tariffAgreement->is_active ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                        <div class="flex items-center gap-2">
                            @if($tariffAgreement->is_active)
                                @svg('heroicon-o-check-circle', 'w-5 h-5 text-green-600')
                                <span class="text-sm font-medium text-green-800">Aktiv</span>
                            @else
                                @svg('heroicon-o-x-circle', 'w-5 h-5 text-red-600')
                                <span class="text-sm font-medium text-red-800">Inaktiv</span>
                            @endif
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Tarifvertrag erstellt</div>
                        <div class="text-[var(--ui-muted)]">{{ $tariffAgreement->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                    @if($tariffAgreement->updated_at != $tariffAgreement->created_at)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Zuletzt bearbeitet</div>
                            <div class="text-[var(--ui-muted)]">{{ $tariffAgreement->updated_at->format('d.m.Y H:i') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
