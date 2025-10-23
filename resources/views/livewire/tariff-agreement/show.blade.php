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
                <div class="grid grid-cols-4 gap-4 mb-6">
                    <x-ui-dashboard-tile
                        title="Tarifgruppen"
                        :count="$tariffAgreement->tariffGroups->count()"
                        icon="squares-2x2"
                        variant="primary"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Tarifstufen"
                        :count="$tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffLevels->count(); })"
                        icon="bars-3"
                        variant="success"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Tarifsätze"
                        :count="$tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffRates->count(); })"
                        icon="banknotes"
                        variant="secondary"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Status"
                        :count="$tariffAgreement->is_active ? 'Aktiv' : 'Inaktiv'"
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

                <!-- Description -->
                @if($tariffAgreement->description)
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Beschreibung</h3>
                        <p class="text-gray-600">{{ $tariffAgreement->description }}</p>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
