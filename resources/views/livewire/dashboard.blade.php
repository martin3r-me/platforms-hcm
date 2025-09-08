<div class="h-full overflow-y-auto p-6">
    <!-- Header mit Datum und Perspektive-Toggle -->
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">HCM Dashboard</h1>
                <p class="text-gray-600">{{ now()->format('l') }}, {{ now()->format('d.m.Y') }}</p>
            </div>
            <div class="d-flex items-center gap-4">
                <!-- Perspektive-Toggle -->
                <div class="d-flex bg-gray-100 rounded-lg p-1">
                    <button 
                        wire:click="$set('perspective', 'personal')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'personal' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-4 h-4')
                            <span>Persönlich</span>
                        </div>
                    </button>
                    <button 
                        wire:click="$set('perspective', 'team')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'team' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-users', 'w-4 h-4')
                            <span>Team</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Perspektive-spezifische Statistiken -->
    @if($perspective === 'personal')
        <!-- Persönliche Perspektive -->
        <div class="mb-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-user', 'w-5 h-5 text-blue-600')
                    <h3 class="text-lg font-semibold text-blue-900">Persönliche Übersicht</h3>
                </div>
                <p class="text-blue-700 text-sm">Deine persönlichen Mitarbeiter und zugewiesenen Unternehmen.</p>
            </div>
        </div>
    @else
        <!-- Team-Perspektive -->
        <div class="mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-users', 'w-5 h-5 text-green-600')
                    <h3 class="text-lg font-semibold text-green-900">Team-Übersicht</h3>
                </div>
                <p class="text-green-700 text-sm">Alle Mitarbeiter und Unternehmen des Teams.</p>
            </div>
        </div>
    @endif

    <!-- Haupt-Statistiken (4x2 Grid) -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <!-- Mitarbeiter -->
        <x-ui-dashboard-tile
            title="Mitarbeiter"
            :count="$totalEmployees"
            icon="users"
            variant="primary"
            size="lg"
            :href="route('hcm.employees.index')"
        />
        
        <!-- Arbeitgeber -->
        <x-ui-dashboard-tile
            title="Arbeitgeber"
            :count="$totalEmployers"
            icon="building-office"
            variant="secondary"
            size="lg"
            :href="route('hcm.employers.index')"
        />
        
        <!-- Mit Kontakten -->
        <x-ui-dashboard-tile
            title="Mit Kontakten"
            :count="$employeesWithContacts"
            icon="user"
            variant="success"
            size="lg"
        />
        
        <!-- Ohne Kontakte -->
        <x-ui-dashboard-tile
            title="Ohne Kontakte"
            :count="$employeesWithoutContacts"
            icon="exclamation-triangle"
            variant="warning"
            size="lg"
        />
    </div>

    <!-- Detaillierte Statistiken (2x3 Grid) -->
    <div class="grid grid-cols-2 gap-6 mb-8">
        <!-- Linke Spalte: Mitarbeiter-Daten -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mitarbeiter-Daten</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Mit Mitarbeiter-Nr."
                    :count="$employeesWithCompanyNumbers"
                    icon="identification"
                    variant="success"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Ohne Mitarbeiter-Nr."
                    :count="$employeesWithoutCompanyNumbers"
                    icon="exclamation-triangle"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Aktive Mitarbeiter"
                    :count="$totalEmployees"
                    icon="check-circle"
                    variant="info"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Verknüpfte Kontakte"
                    :count="$employeesWithContacts"
                    icon="link"
                    variant="danger"
                    size="sm"
                />
            </div>
        </div>

        <!-- Rechte Spalte: Qualitätsmetriken -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Qualitätsmetriken</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Mitarbeiter ohne Kontakt"
                    :count="$employeesWithoutContacts"
                    icon="exclamation-triangle"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Mitarbeiter ohne Nr."
                    :count="$employeesWithoutCompanyNumbers"
                    icon="exclamation-triangle"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Arbeitgeber mit Mitarbeitern"
                    :count="$employersWithEmployees"
                    icon="building-office"
                    variant="success"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Durchschnitt pro Arbeitgeber"
                    :count="$totalEmployers > 0 ? round($totalEmployees / $totalEmployers, 1) : 0"
                    icon="chart-bar"
                    variant="info"
                    size="sm"
                />
            </div>
        </div>
    </div>

    <!-- Aktuelle Aktivitäten -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Neueste Mitarbeiter -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Neueste Mitarbeiter</h3>
                <p class="text-sm text-gray-600 mt-1">Die 5 zuletzt erstellten Mitarbeiter</p>
            </div>
            <div class="p-6">
                @if($recentEmployees->count() > 0)
                    <div class="space-y-4">
                        @foreach($recentEmployees as $employee)
                            <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="d-flex items-center gap-4">
                                    <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                        <x-heroicon-o-user class="w-5 h-5"/>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ $employee->employee_number }}</h4>
                                        <p class="text-sm text-gray-600">{{ $employee->created_at->format('d.m.Y H:i') }}</p>
                                        @if($employee->crmContactLinks->count() > 0)
                                            <p class="text-xs text-green-600">{{ $employee->crmContactLinks->count() }} Kontakt(e) verknüpft</p>
                                        @else
                                            <p class="text-xs text-yellow-600">Kein Kontakt verknüpft</p>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('hcm.employees.show', ['employee' => $employee->id]) }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-on-primary rounded-md hover:bg-primary-dark transition text-sm"
                                   wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Anzeigen</span>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-heroicon-o-user class="w-12 h-12 text-gray-400 mx-auto mb-4"/>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Keine Mitarbeiter</h4>
                        <p class="text-gray-600">Es wurden noch keine Mitarbeiter erstellt.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Top Arbeitgeber nach Mitarbeitern -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Top Arbeitgeber</h3>
                <p class="text-sm text-gray-600 mt-1">Arbeitgeber mit den meisten Mitarbeitern</p>
            </div>
            <div class="p-6">
                @if($topEmployersByEmployees->count() > 0)
                    <div class="space-y-4">
                        @foreach($topEmployersByEmployees as $employer)
                            <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="d-flex items-center gap-4">
                                    <div class="w-10 h-10 bg-secondary text-on-secondary rounded-lg d-flex items-center justify-center">
                                        <x-heroicon-o-building-office class="w-5 h-5"/>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ $employer->display_name }}</h4>
                                        <p class="text-sm text-gray-600">{{ $employer->employees_count }} Mitarbeiter</p>
                                    </div>
                                </div>
                                <a href="{{ route('hcm.employers.show', ['employer' => $employer->id]) }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-secondary text-on-secondary rounded-md hover:bg-secondary-dark transition text-sm"
                                   wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Anzeigen</span>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-heroicon-o-building-office class="w-12 h-12 text-gray-400 mx-auto mb-4"/>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Keine Arbeitgeber</h4>
                        <p class="text-gray-600">Es wurden noch keine Arbeitgeber mit Mitarbeitern erstellt.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Top Arbeitgeber nach Mitarbeiteranzahl -->
    @if($topEmployersByEmployees->count() > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Mitarbeiter-Verteilung</h3>
                <p class="text-sm text-gray-600 mt-1">Verteilung der Mitarbeiter nach Arbeitgebern</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @foreach($topEmployersByEmployees as $employer)
                        <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="d-flex items-center gap-4">
                                <div class="w-10 h-10 bg-secondary text-on-secondary rounded-lg d-flex items-center justify-center">
                                    <x-heroicon-o-building-office class="w-5 h-5"/>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $employer->display_name }}</h4>
                                    <p class="text-sm text-gray-600">{{ $employer->employees_count }} Mitarbeiter</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-secondary">{{ $employer->employees_count }}</div>
                                <div class="text-sm text-gray-600">
                                    {{ $totalEmployees > 0 ? round(($employer->employees_count / $totalEmployees) * 100, 1) : 0 }}%
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>