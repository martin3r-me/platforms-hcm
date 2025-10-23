<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $healthInsuranceCompany->name }}" icon="heroicon-o-heart">
            <div class="flex items-center gap-2">
                <a href="{{ route('hcm.health-insurance-companies.index') }}" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]" wire:navigate>
                    ← Zurück zur Übersicht
                </a>
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- Header --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight">
                        {{ $healthInsuranceCompany->name }}
                    </h1>
                    <div class="flex items-center gap-6 text-sm text-[var(--ui-muted)]">
                        <span class="flex items-center gap-2">
                            @svg('heroicon-o-identification', 'w-4 h-4')
                            Code: {{ $healthInsuranceCompany->code }}
                        </span>
                        @if($healthInsuranceCompany->short_name)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-tag', 'w-4 h-4')
                                {{ $healthInsuranceCompany->short_name }}
                            </span>
                        @endif
                        @if($healthInsuranceCompany->website)
                            <a href="{{ $healthInsuranceCompany->website }}" target="_blank" class="flex items-center gap-2 hover:text-[var(--ui-secondary)]">
                                @svg('heroicon-o-globe-alt', 'w-4 h-4')
                                Website
                            </a>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                        @if($healthInsuranceCompany->is_active) bg-green-100 text-green-800
                        @else bg-red-100 text-red-800 @endif">
                        {{ $healthInsuranceCompany->is_active ? 'Aktiv' : 'Inaktiv' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Statistiken --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-ui-dashboard-tile 
                title="Mitarbeiter" 
                :value="$healthInsuranceCompany->employees->count()"
                icon="heroicon-o-user-group"
                color="blue"
            />
            <x-ui-dashboard-tile 
                title="Aktive Verträge" 
                :value="$healthInsuranceCompany->employees->where('contracts.is_active', true)->count()"
                icon="heroicon-o-document-text"
                color="green"
            />
            <x-ui-dashboard-tile 
                title="Code" 
                :value="$healthInsuranceCompany->code"
                icon="heroicon-o-identification"
                color="purple"
            />
        </div>

        {{-- Kontaktinformationen --}}
        @if($healthInsuranceCompany->phone || $healthInsuranceCompany->email || $healthInsuranceCompany->address)
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <div class="flex items-center gap-2 mb-6">
                    @svg('heroicon-o-phone', 'w-6 h-6 text-blue-600')
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Kontaktinformationen</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($healthInsuranceCompany->phone)
                        <div class="flex items-center gap-3">
                            @svg('heroicon-o-phone', 'w-5 h-5 text-[var(--ui-muted)]')
                            <div>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">Telefon</div>
                                <div class="text-sm text-[var(--ui-muted)]">{{ $healthInsuranceCompany->phone }}</div>
                            </div>
                        </div>
                    @endif
                    
                    @if($healthInsuranceCompany->email)
                        <div class="flex items-center gap-3">
                            @svg('heroicon-o-envelope', 'w-5 h-5 text-[var(--ui-muted)]')
                            <div>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">E-Mail</div>
                                <div class="text-sm text-[var(--ui-muted)]">{{ $healthInsuranceCompany->email }}</div>
                            </div>
                        </div>
                    @endif
                    
                    @if($healthInsuranceCompany->website)
                        <div class="flex items-center gap-3">
                            @svg('heroicon-o-globe-alt', 'w-5 h-5 text-[var(--ui-muted)]')
                            <div>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">Website</div>
                                <a href="{{ $healthInsuranceCompany->website }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
                                    {{ $healthInsuranceCompany->website }}
                                </a>
                            </div>
                        </div>
                    @endif
                    
                    @if($healthInsuranceCompany->address)
                        <div class="flex items-start gap-3">
                            @svg('heroicon-o-map-pin', 'w-5 h-5 text-[var(--ui-muted)]')
                            <div>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">Adresse</div>
                                <div class="text-sm text-[var(--ui-muted)] whitespace-pre-line">{{ $healthInsuranceCompany->address }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Beschreibung --}}
        @if($healthInsuranceCompany->description)
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <div class="flex items-center gap-2 mb-6">
                    @svg('heroicon-o-document-text', 'w-6 h-6 text-green-600')
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Beschreibung</h2>
                </div>
                <div class="text-[var(--ui-muted)] whitespace-pre-line">{{ $healthInsuranceCompany->description }}</div>
            </div>
        @endif

        {{-- Mitarbeiter --}}
        @if($healthInsuranceCompany->employees->count() > 0)
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <div class="flex items-center gap-2 mb-6">
                    @svg('heroicon-o-user-group', 'w-6 h-6 text-purple-600')
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Zugeordnete Mitarbeiter</h2>
                </div>
                
                <div class="space-y-4">
                    @foreach($healthInsuranceCompany->employees as $employee)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                                    @svg('heroicon-o-user', 'w-5 h-5')
                                </div>
                                <div>
                                    <div class="font-medium text-[var(--ui-secondary)]">
                                        {{ $employee->full_name ?? $employee->employee_number }}
                                    </div>
                                    <div class="text-sm text-[var(--ui-muted)]">
                                        Mitarbeiter #{{ $employee->employee_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <a href="{{ route('hcm.employees.show', $employee) }}" 
                                   class="text-sm text-blue-600 hover:text-blue-800" 
                                   wire:navigate>
                                    Anzeigen →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
