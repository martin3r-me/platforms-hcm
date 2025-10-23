<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Tarifstufen" icon="heroicon-o-bars-3" />
    </x-slot>

    <x-ui-page-container>
        <div class="mb-6">
            <x-ui-input-text 
                name="search" 
                placeholder="Tarifstufen durchsuchen..." 
                class="w-64"
            />
        </div>

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="code" :currentSort="$sortField" :sortDirection="$sortDirection">Code</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="name" :currentSort="$sortField" :sortDirection="$sortDirection">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifgruppe</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifvertrag</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="progression_months" :currentSort="$sortField" :sortDirection="$sortDirection">Progression (Monate)</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifsätze</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>
            
            <x-ui-table-body>
                @foreach($tariffLevels as $level)
                    <x-ui-table-row 
                        compact="true"
                        clickable="true" 
                        :href="route('hcm.tariff-levels.show', ['tariffLevel' => $level->id])"
                    >
                        <x-ui-table-cell compact="true">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $level->code }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $level->name }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-gray-500">{{ $level->tariffGroup->name ?? 'N/A' }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-gray-500">{{ $level->tariffGroup->tariffAgreement->name ?? 'N/A' }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($level->progression_months)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $level->progression_months }} Monate
                                </span>
                            @else
                                <span class="text-xs text-gray-400">Endstufe</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                {{ $level->tariffRates->count() }} Sätze
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="right">
                            <a href="{{ route('hcm.tariff-levels.show', $level) }}" 
                               class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                Anzeigen
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $tariffLevels->links() }}
        </div>
    </x-ui-page-container>
</x-ui-page>