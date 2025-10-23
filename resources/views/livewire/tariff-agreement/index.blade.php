<div>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tarifverträge</h1>
                <p class="mt-1 text-sm text-gray-500">Verwaltung der Tarifverträge und Tarifstrukturen</p>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="mb-6">
        <div class="flex items-center space-x-4">
            <div class="flex-1">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Tarifverträge durchsuchen..."
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
            </div>
            <div class="flex items-center space-x-2">
                <select wire:model.live="perPage" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="15">15 pro Seite</option>
                    <option value="25">25 pro Seite</option>
                    <option value="50">50 pro Seite</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tariff Agreements Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('code')">
                            Code
                            @if($sortField === 'code')
                                @if($sortDirection === 'asc')
                                    @svg('heroicons.chevron-up', 'w-4 h-4 inline ml-1')
                                @else
                                    @svg('heroicons.chevron-down', 'w-4 h-4 inline ml-1')
                                @endif
                            @endif
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('name')">
                            Name
                            @if($sortField === 'name')
                                @if($sortDirection === 'asc')
                                    @svg('heroicons.chevron-up', 'w-4 h-4 inline ml-1')
                                @else
                                    @svg('heroicons.chevron-down', 'w-4 h-4 inline ml-1')
                                @endif
                            @endif
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Team
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tarifgruppen
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('is_active')">
                            Status
                            @if($sortField === 'is_active')
                                @if($sortDirection === 'asc')
                                    @svg('heroicons.chevron-up', 'w-4 h-4 inline ml-1')
                                @else
                                    @svg('heroicons.chevron-down', 'w-4 h-4 inline ml-1')
                                @endif
                            @endif
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Aktionen
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($tariffAgreements as $agreement)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $agreement->code }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $agreement->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $agreement->team->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $agreement->tariffGroups->count() }} Gruppen
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($agreement->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Aktiv
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Inaktiv
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('hcm.tariff-agreements.show', $agreement) }}" 
                                   class="text-indigo-600 hover:text-indigo-900">
                                    Anzeigen
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                Keine Tarifverträge gefunden
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $tariffAgreements->links() }}
    </div>
</div>
