<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Tarifgruppen" icon="heroicon-o-squares-2x2" />
    </x-slot>

    <x-ui-page-container>
        <div class="mb-6">
            <x-ui-input-text 
                name="search" 
                placeholder="Tarifgruppen durchsuchen..." 
                class="w-64"
            />
        </div>

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="code" :currentSort="$sortField" :sortDirection="$sortDirection">Code</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="name" :currentSort="$sortField" :sortDirection="$sortDirection">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifvertrag</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Stufen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifsätze</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>
            
            <x-ui-table-body>
                @foreach($tariffGroups as $group)
                    <x-ui-table-row 
                        compact="true"
                        clickable="true" 
                        :href="route('hcm.tariff-groups.show', ['tariffGroup' => $group->id])"
                    >
                        <x-ui-table-cell compact="true">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $group->code }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $group->name }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-gray-500">{{ $group->tariffAgreement->name ?? 'N/A' }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ $group->tariffLevels->count() }} Stufen
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                {{ $group->tariffRates->count() }} Sätze
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="right">
                            <a href="{{ route('hcm.tariff-groups.show', $group) }}" 
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
            {{ $tariffGroups->links() }}
        </div>
    </x-ui-page-container>
</x-ui-page>
