<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Tarifverträge" icon="heroicon-o-document-text" />
    </x-slot>

    <x-ui-page-container>
        <div class="mb-6">
            <x-ui-input-text 
                name="search" 
                placeholder="Tarifverträge durchsuchen..." 
                class="w-64"
            />
        </div>

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="code" :currentSort="$sortField" :sortDirection="$sortDirection">Code</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="name" :currentSort="$sortField" :sortDirection="$sortDirection">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Team</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tarifgruppen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="is_active" :currentSort="$sortField" :sortDirection="$sortDirection">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>
            
            <x-ui-table-body>
                @foreach($tariffAgreements as $agreement)
                    <x-ui-table-row 
                        compact="true"
                        clickable="true" 
                        :href="route('hcm.tariff-agreements.show', ['tariffAgreement' => $agreement->id])"
                    >
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $agreement->code }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $agreement->name }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-gray-500">{{ $agreement->team->name ?? 'N/A' }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $agreement->tariffGroups->count() }} Gruppen
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($agreement->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Aktiv
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Inaktiv
                                </span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="right">
                            <a href="{{ route('hcm.tariff-agreements.show', $agreement) }}" 
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
            {{ $tariffAgreements->links() }}
        </div>
    </x-ui-page-container>
</x-ui-page>
