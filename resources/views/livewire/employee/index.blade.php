<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Mitarbeiter" icon="heroicon-o-user-group">
            <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Übersicht" subtitle="Mitarbeiter verwalten">
            <div class="flex justify-end items-center mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neu
                </x-ui-button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-2">Nr.</th>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Unternehmen</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->employees as $employee)
                            <tr>
                                <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $employee->employee_number }}</td>
                                <td class="px-4 py-2">
                                    @if($employee->crmContactLinks->count() > 0)
                                        <div class="space-y-1">
                                            @foreach($employee->crmContactLinks->take(1) as $link)
                                                <div class="font-medium">{{ $link->contact?->full_name ?? 'Unbekannt' }}</div>
                                                @if($link->contact?->emailAddresses->first())
                                                    <div class="text-xs text-[var(--ui-muted)]">{{ $link->contact->emailAddresses->first()->email_address }}</div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($employee->employer)
                                        <div class="text-sm">{{ $employee->employer->display_name }}</div>
                                    @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <x-ui-badge variant="{{ $employee->is_active ? 'success' : 'secondary' }}" size="xs">
                                        {{ $employee->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <x-ui-button 
                                        size="sm" 
                                        variant="secondary" 
                                        href="{{ route('hcm.employees.show', ['employee' => $employee->id]) }}" 
                                        wire:navigate
                                    >
                                        Bearbeiten
                                    </x-ui-button>
                                </td>
                            </tr>
                            @foreach($employee->contracts as $c)
                                <tr class="bg-[var(--ui-muted-5)]">
                                    <td class="px-4 py-2">
                                        <div class="pl-5 text-xs text-[var(--ui-muted)]">
                                            <span class="align-middle">└</span> 
                                            @svg('heroicon-o-calendar', 'w-3 h-3 inline text-[var(--ui-muted)]') 
                                            {{ optional($c->start_date)->format('d.m.Y') }} – {{ optional($c->end_date)->format('d.m.Y') ?: 'offen' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="pl-5 space-y-1">
                                            @if($c->jobTitles && $c->jobTitles->count() > 0)
                                                <div class="flex flex-wrap gap-1 mb-1">
                                                    <span class="text-xs text-[var(--ui-muted)] mr-1">Stelle:</span>
                                                    @foreach($c->jobTitles as $title)
                                                        <x-ui-badge variant="secondary" size="xs">{{ $title->name }}</x-ui-badge>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($c->jobActivities && $c->jobActivities->count() > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    <span class="text-xs text-[var(--ui-muted)] mr-1">Tätigkeiten:</span>
                                                    @foreach($c->jobActivities as $activity)
                                                        <x-ui-badge variant="secondary" size="xs">{{ $activity->name }}</x-ui-badge>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2">
                                        <x-ui-badge variant="{{ $c->is_active ? 'success' : 'secondary' }}" size="xs">
                                            {{ $c->is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </x-ui-badge>
                                    </td>
                                    <td class="px-4 py-2"></td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center">
                                    @svg('heroicon-o-user-group', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                    <div class="text-sm text-[var(--ui-muted)]">Keine Mitarbeiter gefunden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>
    </x-ui-page-container>

    <!-- Create Employee Modal -->
    <x-ui-modal wire:model="modalShow" size="md">
        <x-slot name="header">Neuer Mitarbeiter</x-slot>
        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center gap-2 mb-1">
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
            <div class="flex justify-end gap-2">
                @if($createStep === 1)
                    <x-ui-button type="button" variant="secondary-outline" wire:click="closeCreateModal">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button type="button" variant="primary" wire:click="nextStep">
                        Weiter
                    </x-ui-button>
                @elseif($createStep === 2)
                    <x-ui-button type="button" variant="secondary-outline" wire:click="prevStep">
                        Zurück
                    </x-ui-button>
                    <x-ui-button type="button" variant="secondary-outline" wire:click="finalizeCreateEmployee">
                        Überspringen
                    </x-ui-button>
                    <x-ui-button type="button" variant="primary" wire:click="finalizeCreateEmployee">
                        Anlegen
                    </x-ui-button>
                @endif
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
