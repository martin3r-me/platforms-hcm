<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="HCM Dashboard" icon="heroicon-o-users">
            <div class="flex items-center gap-4">
                <!-- Perspektive-Toggle -->
                <div class="flex bg-[var(--ui-muted-5)] rounded-lg p-1">
                    <button 
                        wire:click="$set('perspective', 'personal')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'personal' 
                            ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] shadow-sm' 
                            : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'"
                    >
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-4 h-4')
                            <span>Persönlich</span>
                        </div>
                    </button>
                    <button 
                        wire:click="$set('perspective', 'team')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'team' 
                            ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] shadow-sm' 
                            : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'"
                    >
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-users', 'w-4 h-4')
                            <span>Team</span>
                        </div>
                    </button>
                </div>
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <!-- Perspektive-spezifische Statistiken -->
        @if($perspective === 'personal')
            <!-- Persönliche Perspektive -->
            <div class="mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-user', 'w-5 h-5 text-blue-600')
                        <h3 class="text-lg font-semibold text-blue-900">Persönliche Übersicht</h3>
                    </div>
                    <p class="text-blue-700 text-sm">Deine persönlichen Mitarbeiter und zugewiesenen Unternehmen.</p>
                </div>
            </div>
        @else
            <!-- Team-Perspektive -->
            <div class="mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-users', 'w-5 h-5 text-green-600')
                        <h3 class="text-lg font-semibold text-green-900">Team-Übersicht</h3>
                    </div>
                    <p class="text-green-700 text-sm">Alle Mitarbeiter und Unternehmen des Teams.</p>
                </div>
            </div>
        @endif

        <!-- Haupt-Statistiken (4x2 Grid) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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

        <!-- Detaillierte Statistiken -->
        <x-ui-detail-stats-grid cols="2" gap="6">
            <x-slot:left>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Mitarbeiter-Daten</h3>
                <x-ui-form-grid :cols="2" :gap="3">
                    <x-ui-dashboard-tile title="Mit Mitarbeiter-Nr." :count="$employeesWithCompanyNumbers" icon="identification" variant="success" size="sm" />
                    <x-ui-dashboard-tile title="Ohne Mitarbeiter-Nr." :count="$employeesWithoutCompanyNumbers" icon="exclamation-triangle" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Aktive Mitarbeiter" :count="$totalEmployees" icon="check-circle" variant="info" size="sm" />
                    <x-ui-dashboard-tile title="Verknüpfte Kontakte" :count="$employeesWithContacts" icon="link" variant="danger" size="sm" />
                </x-ui-form-grid>
            </x-slot:left>
            <x-slot:right>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Qualitätsmetriken</h3>
                <x-ui-form-grid :cols="2" :gap="3">
                    <x-ui-dashboard-tile title="Mitarbeiter ohne Kontakt" :count="$employeesWithoutContacts" icon="exclamation-triangle" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Mitarbeiter ohne Nr." :count="$employeesWithoutCompanyNumbers" icon="exclamation-triangle" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Arbeitgeber mit Mitarbeitern" :count="$employersWithEmployees" icon="building-office" variant="success" size="sm" />
                    <x-ui-dashboard-tile title="Durchschnitt pro Arbeitgeber" :count="$totalEmployers > 0 ? round($totalEmployees / $totalEmployers, 1) : 0" icon="chart-bar" variant="info" size="sm" />
                </x-ui-form-grid>
            </x-slot:right>
        </x-ui-detail-stats-grid>

        <!-- Aktuelle Aktivitäten -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Neueste Mitarbeiter -->
            <x-ui-panel title="Neueste Mitarbeiter" subtitle="Die 5 zuletzt erstellten Mitarbeiter">
                @if($recentEmployees->count() > 0)
                    <div class="space-y-4">
                        @foreach($recentEmployees as $employee)
                            <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg hover:bg-[var(--ui-muted-5)] transition">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-lg flex items-center justify-center">
                                        @svg('heroicon-o-user', 'w-5 h-5')
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-[var(--ui-secondary)]">{{ $employee->employee_number }}</h4>
                                        <p class="text-sm text-[var(--ui-muted)]">{{ $employee->created_at->format('d.m.Y H:i') }}</p>
                                        @if($employee->crmContactLinks->count() > 0)
                                            <p class="text-xs text-green-600">{{ $employee->crmContactLinks->count() }} Kontakt(e) verknüpft</p>
                                        @else
                                            <p class="text-xs text-yellow-600">Kein Kontakt verknüpft</p>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('hcm.employees.show', ['employee' => $employee->id]) }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-md hover:bg-[var(--ui-primary)]/90 transition text-sm"
                                   wire:navigate>
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Anzeigen</span>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        @svg('heroicon-o-user', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-4')
                        <h4 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Keine Mitarbeiter</h4>
                        <p class="text-[var(--ui-muted)]">Es wurden noch keine Mitarbeiter erstellt.</p>
                    </div>
                @endif
            </x-ui-panel>

            <!-- Top Arbeitgeber nach Mitarbeitern -->
            <x-ui-panel title="Top Arbeitgeber" subtitle="Arbeitgeber mit den meisten Mitarbeitern">
                @if($topEmployersByEmployees->count() > 0)
                    <div class="space-y-4">
                        @foreach($topEmployersByEmployees as $employer)
                            <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg hover:bg-[var(--ui-muted-5)] transition">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-[var(--ui-secondary)] text-[var(--ui-on-secondary)] rounded-lg flex items-center justify-center">
                                        @svg('heroicon-o-building-office', 'w-5 h-5')
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-[var(--ui-secondary)]">{{ $employer->display_name }}</h4>
                                        <p class="text-sm text-[var(--ui-muted)]">{{ $employer->employees_count }} Mitarbeiter</p>
                                    </div>
                                </div>
                                <a href="{{ route('hcm.employers.show', ['employer' => $employer->id]) }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-[var(--ui-secondary)] text-[var(--ui-on-secondary)] rounded-md hover:bg-[var(--ui-secondary)]/90 transition text-sm"
                                   wire:navigate>
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Anzeigen</span>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        @svg('heroicon-o-building-office', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-4')
                        <h4 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Keine Arbeitgeber</h4>
                        <p class="text-[var(--ui-muted)]">Es wurden noch keine Arbeitgeber mit Mitarbeitern erstellt.</p>
                    </div>
                @endif
            </x-ui-panel>
        </div>

        <!-- Top Arbeitgeber nach Mitarbeiteranzahl -->
        @if($topEmployersByEmployees->count() > 0)
            <x-ui-panel title="Mitarbeiter-Verteilung" subtitle="Verteilung der Mitarbeiter nach Arbeitgebern">
                <div class="space-y-4">
                    @foreach($topEmployersByEmployees as $employer)
                        <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-[var(--ui-secondary)] text-[var(--ui-on-secondary)] rounded-lg flex items-center justify-center">
                                    @svg('heroicon-o-building-office', 'w-5 h-5')
                                </div>
                                <div>
                                    <h4 class="font-medium text-[var(--ui-secondary)]">{{ $employer->display_name }}</h4>
                                    <p class="text-sm text-[var(--ui-muted)]">{{ $employer->employees_count }} Mitarbeiter</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $employer->employees_count }}</div>
                                <div class="text-sm text-[var(--ui-muted)]">
                                    {{ $totalEmployees > 0 ? round(($employer->employees_count / $totalEmployees) * 100, 1) : 0 }}%
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui-panel>
        @endif
    </x-ui-page-container>

    {{-- Left Sidebar - Dashboard Übersicht --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Dashboard Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Schnellzugriff --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Schnellzugriff</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" :href="route('hcm.employees.index')" wire:navigate class="w-full justify-start">
                            @svg('heroicon-o-user-group', 'w-4 h-4')
                            <span class="ml-2">Alle Mitarbeiter</span>
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" :href="route('hcm.employers.index')" wire:navigate class="w-full justify-start">
                            @svg('heroicon-o-building-office', 'w-4 h-4')
                            <span class="ml-2">Alle Arbeitgeber</span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 p-3">
                            <div class="text-xs text-[var(--ui-muted)]">Gesamt Mitarbeiter</div>
                            <div class="text-lg font-bold text-[var(--ui-primary)]">{{ $totalEmployees }}</div>
                        </div>
                        <div class="bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 p-3">
                            <div class="text-xs text-[var(--ui-muted)]">Gesamt Arbeitgeber</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalEmployers }}</div>
                        </div>
                        <div class="bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 p-3">
                            <div class="text-xs text-[var(--ui-muted)]">Mit Kontakten</div>
                            <div class="text-lg font-bold text-green-600">{{ $employeesWithContacts }}</div>
                        </div>
                    </div>
                </div>

                {{-- Qualitätsmetriken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Qualität</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">Ohne Kontakte</span>
                            <span class="text-sm text-[var(--ui-muted)]">{{ $employeesWithoutContacts }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">Ohne Mitarbeiter-Nr.</span>
                            <span class="text-sm text-[var(--ui-muted)]">{{ $employeesWithoutCompanyNumbers }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Right Sidebar - Aktivitäten --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten & Timeline" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                {{-- Recent Activities --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-3 text-sm">
                        <div class="text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
                    </div>
                </div>

                {{-- Performance Übersicht --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Performance</h3>
                    <div class="space-y-3">
                        <div class="bg-[var(--ui-muted-5)] rounded-lg p-3">
                            <div class="text-lg font-bold text-[var(--ui-primary)]">{{ $totalEmployees }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Gesamt Mitarbeiter</div>
                        </div>
                        <div class="bg-[var(--ui-muted-5)] rounded-lg p-3">
                            <div class="text-lg font-bold text-green-600">{{ $employeesWithContacts }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Mit Kontakten</div>
                        </div>
                        <div class="bg-[var(--ui-muted-5)] rounded-lg p-3">
                            <div class="text-lg font-bold text-blue-600">{{ $totalEmployers }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">Arbeitgeber</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>