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

        <x-ui-table>
            <x-ui-table-header>
                <x-ui-table-header-cell sortable="true" sortField="code" :currentSort="$sortField" :sortDirection="$sortDirection">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-tag', 'w-4 h-4')
                        Code
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell sortable="true" sortField="name" :currentSort="$sortField" :sortDirection="$sortDirection">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4')
                        Name
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell>
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-document-text', 'w-4 h-4')
                        Tarifvertrag
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell>
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-bars-3', 'w-4 h-4')
                        Stufen
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell>
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-banknotes', 'w-4 h-4')
                        Tarifsätze
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell align="right">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                        Aktionen
                    </div>
                </x-ui-table-header-cell>
            </x-ui-table-header>
            
            <x-ui-table-body>
                @foreach($tariffGroups as $group)
                    <x-ui-table-row 
                        clickable="true" 
                        :href="route('hcm.tariff-groups.show', ['tariffGroup' => $group->id])"
                    >
                        <x-ui-table-cell>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                {{ $group->code }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <div class="font-medium text-gray-900">{{ $group->name }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-document-text', 'w-4 h-4 text-gray-400')
                                <span class="text-sm text-gray-600">{{ $group->tariffAgreement->name ?? 'N/A' }}</span>
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                @svg('heroicon-o-bars-3', 'w-3 h-3')
                                {{ $group->tariffLevels->count() }} Stufen
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                @svg('heroicon-o-banknotes', 'w-3 h-3')
                                {{ $group->tariffRates->count() }} Sätze
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell align="right">
                            <a href="{{ route('hcm.tariff-groups.show', $group) }}" 
                               class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                @svg('heroicon-o-eye', 'w-4 h-4')
                                Anzeigen
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>

    </x-ui-page-container>
</x-ui-page>
