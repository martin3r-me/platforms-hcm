<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar 
            title="Tarifsatz: {{ number_format((float)$tariffRate->amount, 2, ',', '.') }} €" 
            icon="heroicon-o-banknotes"
            :breadcrumbs="[
                ['title' => 'Tarifsätze', 'route' => 'hcm.tariff-rates.index'],
                ['title' => number_format((float)$tariffRate->amount, 2, ',', '.') . ' €']
            ]"
        />
    </x-slot>

    <x-ui-page-container>
        <!-- Tariff Rate Details -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Tarifsatz Details</h3>
            </div>
            <div class="px-6 py-4">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Betrag</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="text-2xl font-bold text-green-600">
                                {{ number_format((float)$tariffRate->amount, 2, ',', '.') }} €
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            @if($tariffRate->is_current)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Aktuell
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    Historisch
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tarifstufe</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="{{ route('hcm.tariff-levels.show', $tariffRate->tariffLevel) }}" 
                               class="text-indigo-600 hover:text-indigo-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $tariffRate->tariffLevel->code }}
                                </span>
                                {{ $tariffRate->tariffLevel->name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tarifgruppe</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="{{ route('hcm.tariff-groups.show', $tariffRate->tariffLevel->tariffGroup) }}" 
                               class="text-indigo-600 hover:text-indigo-900">
                                {{ $tariffRate->tariffLevel->tariffGroup->name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tarifvertrag</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="{{ route('hcm.tariff-agreements.show', $tariffRate->tariffLevel->tariffGroup->tariffAgreement) }}" 
                               class="text-indigo-600 hover:text-indigo-900">
                                {{ $tariffRate->tariffLevel->tariffGroup->tariffAgreement->name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Gültig ab</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $tariffRate->valid_from->format('d.m.Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Gültig bis</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $tariffRate->valid_until ? $tariffRate->valid_until->format('d.m.Y') : 'Unbegrenzt' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Erstellt</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $tariffRate->created_at->format('d.m.Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Related Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Tariff Level Info -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Tarifstufe</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Code</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffRate->tariffLevel->code }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffRate->tariffLevel->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Progression</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($tariffRate->tariffLevel->progression_months)
                                    {{ $tariffRate->tariffLevel->progression_months }} Monate
                                @else
                                    Endstufe
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Tariff Group Info -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Tarifgruppe</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Code</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffRate->tariffLevel->tariffGroup->code }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffRate->tariffLevel->tariffGroup->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Beschreibung</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tariffRate->tariffLevel->tariffGroup->description ?? 'Keine Beschreibung' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>