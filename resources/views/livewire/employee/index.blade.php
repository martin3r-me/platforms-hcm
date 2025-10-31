<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Mitarbeiter" icon="heroicon-o-user-group" />
    </x-slot>

    <x-ui-page-container>
        <div class="mb-6">
            <x-ui-input-text 
                name="search" 
                placeholder="Suche Mitarbeiter..." 
                class="w-64"
            />
        </div>
    
    <x-ui-table compact="true">
        <x-ui-table-header muted="true">
            <x-ui-table-header-cell compact="true" sortable="true" sortField="employee_number" :currentSort="$sortField" :sortDirection="$sortDirection">Nr.</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Kontakt</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Unternehmen</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Stelle</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Tätigkeiten</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" sortable="true" sortField="is_active" :currentSort="$sortField" :sortDirection="$sortDirection">Status</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
        </x-ui-table-header>
        
        <x-ui-table-body>
            @foreach($this->employees as $employee)
                <x-ui-table-row 
                    compact="true"
                    clickable="true" 
                    :href="route('hcm.employees.show', ['employee' => $employee->id])"
                >
                    <x-ui-table-cell compact="true">
                        <div class="font-medium">{{ $employee->employee_number }}</div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($employee->crmContactLinks->count() > 0)
                            <div class="space-y-1">
                                @foreach($employee->crmContactLinks->take(2) as $link)
                                    <div class="text-xs d-flex items-center gap-1">
                                        @svg('heroicon-o-user', 'w-3 h-3 text-muted')
                                        {{ $link->contact?->full_name ?? 'Unbekannt' }}
                                    </div>
                                @endforeach
                                @if($employee->crmContactLinks->count() > 2)
                                    <div class="text-xs text-muted">+{{ $employee->crmContactLinks->count() - 2 }} weitere</div>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-muted">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($employee->employer)
                            <div class="text-xs d-flex items-center gap-1">
                                @svg('heroicon-o-building-office', 'w-3 h-3 text-muted')
                                {{ $employee->employer->display_name }}
                            </div>
                        @else
                            <span class="text-xs text-muted">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @php($contract = $employee->contracts->first())
                        @if($contract)
                            <x-ui-input-select 
                                name="job_title_{{ $contract->id }}"
                                :options="$this->availableJobTitles"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="Ohne Stelle"
                                :value="$contract->jobTitles->first()?->id"
                                wire:change="updateContractTitle({{ $contract->id }}, $event.target.value)"
                                class="w-44"
                            />
                        @else
                            <span class="text-xs text-muted">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @php($contract = $employee->contracts->first())
                        @if($contract)
                            <x-ui-input-select 
                                name="job_activities_{{ $contract->id }}[]"
                                :options="$this->availableJobActivities"
                                optionValue="id"
                                optionLabel="name"
                                :multiple="true"
                                :value="$contract->jobActivities->pluck('id')->all()"
                                wire:change="updateContractActivities({{ $contract->id }}, Array.from($event.target.selectedOptions).map(o=>o.value))"
                                class="w-56"
                            />
                        @else
                            <span class="text-xs text-muted">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <x-ui-badge variant="{{ $employee->is_active ? 'success' : 'secondary' }}" size="sm">
                            {{ $employee->is_active ? 'Aktiv' : 'Inaktiv' }}
                        </x-ui-badge>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true" align="right">
                        <x-ui-button 
                            size="sm" 
                            variant="secondary" 
                            href="{{ route('hcm.employees.show', ['employee' => $employee->id]) }}" 
                            wire:navigate
                        >
                            Bearbeiten
                        </x-ui-button>
                    </x-ui-table-cell>
                </x-ui-table-row>
                @foreach($employee->contracts as $c)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <div class="pl-5 text-xs text-muted"><span class="align-middle">└</span> @svg('heroicon-o-calendar', 'w-3 h-3 inline text-muted') {{ optional($c->start_date)->format('d.m.Y') }} – {{ optional($c->end_date)->format('d.m.Y') ?: 'offen' }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true"></x-ui-table-cell>
                        <x-ui-table-cell compact="true"></x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-input-select 
                                name="job_title_row_{{ $c->id }}"
                                :options="$this->availableJobTitles"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="Ohne Stelle"
                                :value="$c->jobTitles->first()?->id"
                                wire:change="updateContractTitle({{ $c->id }}, $event.target.value)"
                                class="w-44"
                            />
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-input-select 
                                name="job_activities_row_{{ $c->id }}[]"
                                :options="$this->availableJobActivities"
                                optionValue="id"
                                optionLabel="name"
                                :multiple="true"
                                :value="$c->jobActivities->pluck('id')->all()"
                                wire:change="updateContractActivities({{ $c->id }}, Array.from($event.target.selectedOptions).map(o=>o.value))"
                                class="w-full max-w-xl"
                            />
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true"></x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="right"></x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            @endforeach
        </x-ui-table-body>
    </x-ui-table>

    <!-- Create Employee Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="md"
    >
        <x-slot name="header">
            Mitarbeiter anlegen
        </x-slot>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-1">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900">Kurzinfo</h4>
                </div>
                <p class="text-blue-700 text-sm">Die Mitarbeiter-Nummer wird automatisch aus den Einstellungen des gewählten Arbeitgebers generiert. Optional kannst du im nächsten Schritt einen CRM‑Kontakt verknüpfen.</p>
            </div>

            @if($createStep === 1)
                <div class="space-y-4">
                    <x-ui-input-select
                        name="employer_id"
                        label="Arbeitgeber"
                        :options="$this->availableEmployers"
                        optionValue="id"
                        optionLabel="display_name"
                        :nullable="false"
                        wire:model.live="employer_id"
                        required
                    />
                </div>
            @elseif($createStep === 2)
                <div class="space-y-4">
                    <x-ui-input-select
                        name="contact_id"
                        label="CRM‑Kontakt (optional)"
                        :options="$this->availableContacts"
                        optionValue="id"
                        optionLabel="display_name"
                        :nullable="true"
                        nullLabel="Ohne Kontakt"
                        wire:model.live="contact_id"
                    />
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-between items-center w-100">
                <div></div>
                <div class="d-flex justify-end gap-2">
                    @if($createStep === 1)
                        <x-ui-button 
                            type="button" 
                            variant="secondary-outline" 
                            @click="$wire.closeCreateModal()"
                        >
                            Abbrechen
                        </x-ui-button>
                        <x-ui-button type="button" variant="primary" wire:click="nextStep">
                            Weiter
                        </x-ui-button>
                    @elseif($createStep === 2)
                        <x-ui-button 
                            type="button" 
                            variant="secondary-outline" 
                            @click="$wire.prevStep()"
                        >
                            Zurück
                        </x-ui-button>
                        <x-ui-button 
                            type="button" 
                            variant="secondary-outline" 
                            @click="$wire.finalizeCreateEmployee()"
                        >
                            Überspringen
                        </x-ui-button>
                        <x-ui-button type="button" variant="primary" wire:click="finalizeCreateEmployee">
                            Anlegen
                        </x-ui-button>
                    @endif
                </div>
            </div>
        </x-slot>
    </x-ui-modal>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" wire:click="openCreateModal" class="w-full justify-start">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span class="ml-2">Neuer Mitarbeiter</span>
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Hinweise</h3>
                    <div class="space-y-3 text-sm text-[var(--ui-muted)]">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            Mitarbeiter-Nummern werden aus den Arbeitgeber-Einstellungen generiert.
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-3 text-sm">
                        <div class="text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

