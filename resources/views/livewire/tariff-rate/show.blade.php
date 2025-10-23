<div>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tarifsatz</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $tariffRate->tariffGroup->name }} - {{ $tariffRate->tariffLevel->name }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    @svg('heroicons.banknotes', 'w-4 h-4 mr-1')
                    {{ number_format($tariffRate->amount, 2, ',', '.') }} €
                </span>
            </div>
        </div>
    </div>

    <!-- Rate Details -->
    <div class="mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Tarifsatz Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Betrag</p>
                    <p class="text-3xl font-bold text-green-600">{{ number_format($tariffRate->amount, 2, ',', '.') }} €</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Status</p>
                    @php
                        $now = now();
                        $validFrom = $tariffRate->valid_from ? \Carbon\Carbon::parse($tariffRate->valid_from) : null;
                        $validTo = $tariffRate->valid_to ? \Carbon\Carbon::parse($tariffRate->valid_to) : null;
                        
                        $isValid = (!$validFrom || $validFrom <= $now) && (!$validTo || $validTo >= $now);
                    @endphp
                    
                    @if($isValid)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            @svg('heroicons.check-circle', 'w-4 h-4 mr-1')
                            Aktiv
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            @svg('heroicons.x-circle', 'w-4 h-4 mr-1')
                            Inaktiv
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Validity Period -->
    <div class="mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Gültigkeitszeitraum</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Gültig von</p>
                    <p class="text-lg font-semibold text-gray-900">
                        {{ $tariffRate->valid_from ? \Carbon\Carbon::parse($tariffRate->valid_from)->format('d.m.Y') : 'Nicht festgelegt' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Gültig bis</p>
                    <p class="text-lg font-semibold text-gray-900">
                        {{ $tariffRate->valid_to ? \Carbon\Carbon::parse($tariffRate->valid_to)->format('d.m.Y') : 'Unbegrenzt' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tariff Group and Level Info -->
    <div class="mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Tarifgruppe & Stufe</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Tarifgruppe</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $tariffRate->tariffGroup->name }}</p>
                    <p class="text-sm text-gray-500">{{ $tariffRate->tariffGroup->code }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Tarifstufe</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $tariffRate->tariffLevel->name }}</p>
                    <p class="text-sm text-gray-500">{{ $tariffRate->tariffLevel->code }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Tarifvertrag</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $tariffRate->tariffGroup->tariffAgreement->name ?? 'N/A' }}</p>
                    <p class="text-sm text-gray-500">{{ $tariffRate->tariffGroup->tariffAgreement->code ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Progression Info -->
    @if($tariffRate->tariffLevel->progression_months !== 999)
        <div class="mb-8">
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Progression</h3>
                <div class="flex items-center space-x-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Progression (Monate)</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $tariffRate->tariffLevel->progression_months }} Monate</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Status</p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            @svg('heroicons.arrow-right', 'w-4 h-4 mr-1')
                            Progression möglich
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
