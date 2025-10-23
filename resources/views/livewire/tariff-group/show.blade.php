<div>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $tariffGroup->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Tarifgruppe: {{ $tariffGroup->code }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    @svg('heroicons.squares-2x2', 'w-4 h-4 mr-1')
                    {{ $tariffGroup->code }}
                </span>
            </div>
        </div>
    </div>

    <!-- Tariff Levels -->
    <div class="mb-8">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Tarifstufen</h2>
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Stufe
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Progression (Monate)
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tariffGroup->tariffLevels as $level)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $level->code }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $level->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($level->progression_months === 999)
                                        <span class="text-red-600 font-medium">Endstufe</span>
                                    @else
                                        {{ $level->progression_months }} Monate
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($level->progression_months === 999)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Endstufe
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Progression
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
                                Stufe
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Betrag
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gültig von
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gültig bis
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tariffGroup->tariffRates as $rate)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $rate->tariffLevel->code }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="font-medium">{{ number_format($rate->amount, 2, ',', '.') }} €</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $rate->valid_from ? \Carbon\Carbon::parse($rate->valid_from)->format('d.m.Y') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $rate->valid_to ? \Carbon\Carbon::parse($rate->valid_to)->format('d.m.Y') : 'Unbegrenzt' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @svg('heroicons.bars-3', 'h-8 w-8 text-blue-600')
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tarifstufen</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $tariffGroup->tariffLevels->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @svg('heroicons.banknotes', 'h-8 w-8 text-green-600')
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tarifsätze</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $tariffGroup->tariffRates->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @svg('heroicons.document-text', 'h-8 w-8 text-yellow-600')
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tarifvertrag</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $tariffGroup->tariffAgreement->name ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
