<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar 
            title="{{ $tariffLevel->name }}" 
            icon="heroicon-o-bars-3"
            :breadcrumbs="[
                ['title' => 'Tarifstufen', 'route' => 'hcm.tariff-levels.index'],
                ['title' => $tariffLevel->name]
            ]"
        />
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
                                        <div class="text-sm font-medium">{{ number_format($rate->amount, 2, ',', '.') }} €</div>
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
</x-ui-page>