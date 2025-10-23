<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Tarifsätze" icon="heroicon-o-banknotes" />
    </x-slot>

    <x-ui-page-container>
        <div class="mb-6">
            <x-ui-input-text 
                name="search" 
                placeholder="Tarifsätze durchsuchen..." 
                class="w-64"
            />
        </div>

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="amount" :currentSort="$sortField" :sortDirection="$sortDirection">Betrag (€)</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifstufe</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifgruppe</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifvertrag</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="valid_from" :currentSort="$sortField" :sortDirection="$sortDirection">Gültig ab</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>
            
            <x-ui-table-body>
                @foreach($tariffRates as $rate)
                    <x-ui-table-row 
                        compact="true"
                        clickable="true" 
                        :href="route('hcm.tariff-rates.show', ['tariffRate' => $rate->id])"
                    >
                        <x-ui-table-cell compact="true">
                            <div class="text-sm font-medium">{{ number_format((float)$rate->amount, 2, ',', '.') }} €</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $rate->tariffLevel->code }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-gray-500">{{ $rate->tariffLevel->tariffGroup->name ?? 'N/A' }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-gray-500">{{ $rate->tariffLevel->tariffGroup->tariffAgreement->name ?? 'N/A' }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-gray-500">{{ $rate->valid_from->format('d.m.Y') }}</div>
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
                        <x-ui-table-cell compact="true" align="right">
                            <a href="{{ route('hcm.tariff-rates.show', $rate) }}" 
                               class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                Anzeigen
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>

    </x-ui-page-container>
</x-ui-page>