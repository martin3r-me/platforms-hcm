<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $tariffGroup->name }}" icon="heroicon-o-squares-2x2">
            <div class="flex items-center gap-2">
                <a href="{{ route('hcm.tariff-groups.index') }}" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]" wire:navigate>
                    ← Tarifgruppen
                </a>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $tariffGroup->code }}
                </span>
            </div>
        </x-ui-page-navbar>
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
                    
                    <x-ui-dashboard-tile
                        title="Status"
                        :count="(string) ($tariffGroup->tariffAgreement->is_active ? 'Aktiv' : 'Inaktiv')"
                        icon="check-circle"
                        variant="info"
                        size="sm"
                    />
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
                                {{ round((float) ($tariffGroup->tariffLevels->where('progression_months', '!=', 999)->avg('progression_months') ?? 0)) }}
                            </div>
                            <div class="text-sm text-purple-700">Ø Progression (Monate)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
