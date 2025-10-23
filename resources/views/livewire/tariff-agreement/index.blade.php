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
                        @svg('heroicon-o-document-text', 'w-4 h-4')
                        Name
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell>
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-building-office', 'w-4 h-4')
                        Team
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell>
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4')
                        Tarifgruppen
                    </div>
                </x-ui-table-header-cell>
                <x-ui-table-header-cell sortable="true" sortField="is_active" :currentSort="$sortField" :sortDirection="$sortDirection">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-check-circle', 'w-4 h-4')
                        Status
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
                @foreach($tariffAgreements as $agreement)
                    <x-ui-table-row 
                        clickable="true" 
                        :href="route('hcm.tariff-agreements.show', ['tariffAgreement' => $agreement->id])"
                    >
                        <x-ui-table-cell>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    {{ $agreement->code }}
                                </span>
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <div class="font-medium text-gray-900">{{ $agreement->name }}</div>
                            @if($agreement->description)
                                <div class="text-sm text-gray-500 mt-1">{{ Str::limit($agreement->description, 50) }}</div>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-building-office', 'w-4 h-4 text-gray-400')
                                <span class="text-sm text-gray-600">{{ $agreement->team->name ?? 'N/A' }}</span>
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                @svg('heroicon-o-squares-2x2', 'w-3 h-3')
                                {{ $agreement->tariffGroups->count() }} Gruppen
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell>
                            @if($agreement->is_active)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    @svg('heroicon-o-check-circle', 'w-3 h-3')
                                    Aktiv
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    @svg('heroicon-o-x-circle', 'w-3 h-3')
                                    Inaktiv
                                </span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell align="right">
                            <a href="{{ route('hcm.tariff-agreements.show', $agreement) }}" 
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
