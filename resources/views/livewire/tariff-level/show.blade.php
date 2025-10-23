<div>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $tariffLevel->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Tarifstufe: {{ $tariffLevel->code }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                    @svg('heroicons.bars-3', 'w-4 h-4 mr-1')
                    {{ $tariffLevel->code }}
                </span>
            </div>
        </div>
    </div>

    <!-- Progression Info -->
    <div class="mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Progression</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Progression (Monate)</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        @if($tariffLevel->progression_months === 999)
                            <span class="text-red-600">Endstufe</span>
                        @else
                            {{ $tariffLevel->progression_months }} Monate
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Status</p>
                    <p class="text-lg font-semibold">
                        @if($tariffLevel->progression_months === 999)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                @svg('heroicons.x-circle', 'w-4 h-4 mr-1')
                                Endstufe
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                @svg('heroicons.arrow-right', 'w-4 h-4 mr-1')
                                Progression möglich
                            </span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tariff Group Info -->
    <div class="mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Tarifgruppe</h3>
            <div class="flex items-center space-x-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Gruppe</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $tariffLevel->tariffGroup->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Code</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $tariffLevel->tariffGroup->code }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Tarifvertrag</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $tariffLevel->tariffGroup->tariffAgreement->name ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tariff Rates -->
    <div class="mb-8">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Tarifsätze</h2>
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Betrag
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gültig von
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gültig bis
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($tariffLevel->tariffRates as $rate)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <span class="text-lg font-bold text-green-600">{{ number_format($rate->amount, 2, ',', '.') }} €</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $rate->valid_from ? \Carbon\Carbon::parse($rate->valid_from)->format('d.m.Y') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $rate->valid_to ? \Carbon\Carbon::parse($rate->valid_to)->format('d.m.Y') : 'Unbegrenzt' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $now = now();
                                        $validFrom = $rate->valid_from ? \Carbon\Carbon::parse($rate->valid_from) : null;
                                        $validTo = $rate->valid_to ? \Carbon\Carbon::parse($rate->valid_to) : null;
                                        
                                        $isValid = (!$validFrom || $validFrom <= $now) && (!$validTo || $validTo >= $now);
                                    @endphp
                                    
                                    @if($isValid)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Aktiv
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inaktiv
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Keine Tarifsätze gefunden
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
