<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Tarifsätze" icon="heroicon-o-banknotes" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-base font-semibold text-gray-900 dark:text-white">Tarifsätze</h1>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Übersicht aller Tarifsätze mit Beträgen und Gültigkeitsdaten.</p>
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                    <div class="flex gap-3">
                        <x-ui-input-text 
                            name="search" 
                            placeholder="Tarifsätze durchsuchen..." 
                            class="w-64"
                        />
                        <button type="button" class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                            Neuer Tarifsatz
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                            <thead>
                                <tr>
                                    <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-6 lg:pl-8 dark:text-white">Betrag (€)</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Tarifstufe</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Tarifgruppe</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Gültig ab</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                                    <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-6 lg:pr-8">
                                        <span class="sr-only">Aktionen</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                                @foreach($tariffRates as $rate)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-6 lg:pl-8 dark:text-white">
                                            <div class="flex items-center gap-2">
                                                @svg('heroicon-o-banknotes', 'w-4 h-4 text-green-500')
                                                <span class="text-lg font-semibold text-green-600 dark:text-green-400">{{ number_format((float)$rate->amount, 2, ',', '.') }} €</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                                {{ $rate->tariffLevel->code }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            <div class="flex items-center gap-2">
                                                @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-gray-400')
                                                {{ $rate->tariffLevel->tariffGroup->name ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            <div class="flex items-center gap-2">
                                                @svg('heroicon-o-calendar', 'w-4 h-4 text-gray-400')
                                                {{ $rate->valid_from->format('d.m.Y') }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            @if($rate->is_current)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    @svg('heroicon-o-check-circle', 'w-3 h-3')
                                                    Aktuell
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                    @svg('heroicon-o-clock', 'w-3 h-3')
                                                    Historisch
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-6 lg:pr-8">
                                            <a href="{{ route('hcm.tariff-rates.show', $rate) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                Anzeigen<span class="sr-only">, {{ number_format((float)$rate->amount, 2, ',', '.') }} €</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>