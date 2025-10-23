<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar 
            title="{{ $tariffAgreement->name }}" 
            icon="heroicon-o-document-text"
            :breadcrumbs="[
                ['title' => 'Tarifverträge', 'route' => 'hcm.tariff-agreements.index'],
                ['title' => $tariffAgreement->name]
            ]"
        />
    </x-slot>

    <x-ui-page-container>
        <!-- Status Badge -->
        <div class="mb-6">
            @if($tariffAgreement->is_active)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    @svg('heroicon-o-check-circle', 'w-4 h-4 mr-1')
                    Aktiv
                </span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    @svg('heroicon-o-x-circle', 'w-4 h-4 mr-1')
                    Inaktiv
                </span>
            @endif
        </div>

    <!-- Tariff Groups -->
    <div class="mb-8">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Tarifgruppen</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($tariffAgreement->tariffGroups as $group)
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-gray-900">{{ $group->name }}</h3>
                        <span class="text-xs text-gray-500">{{ $group->code }}</span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p>{{ $group->tariffLevels->count() }} Stufen</p>
                        <p>{{ $group->tariffRates->count() }} Tarifsätze</p>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('hcm.tariff-groups.show', $group) }}" 
                           class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                            Details anzeigen →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @svg('heroicon-o-squares-2x2', 'h-8 w-8 text-blue-600')
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tarifgruppen</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $tariffAgreement->tariffGroups->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @svg('heroicon-o-bars-3', 'h-8 w-8 text-green-600')
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tarifstufen</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffLevels->count(); }) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @svg('heroicon-o-banknotes', 'h-8 w-8 text-yellow-600')
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tarifsätze</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $tariffAgreement->tariffGroups->sum(function($group) { return $group->tariffRates->count(); }) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Description -->
    @if($tariffAgreement->description)
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Beschreibung</h3>
            <p class="text-gray-600">{{ $tariffAgreement->description }}</p>
        </div>
    @endif
    </x-ui-page-container>
</x-ui-page>
