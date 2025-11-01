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
                            <th class="px-4 py-3">Nr.</th>
                            <th class="px-4 py-3">Name & Kontakt</th>
                            <th class="px-4 py-3">Unternehmen</th>
                            <th class="px-4 py-3">Geburtsdatum</th>
                            <th class="px-4 py-3">Verträge</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->employees as $employee)
                            @php
                                $primaryContact = $employee->crmContactLinks->first()?->contact;
                                $primaryEmail = $primaryContact?->emailAddresses->first()?->email_address;
                                $birthDate = $primaryContact?->birth_date ?? $employee->birth_date;
                                $activeContracts = $employee->contracts->where('is_active', true);
                            @endphp
                            <tr class="hover:bg-[var(--ui-muted-5)] transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-[var(--ui-secondary)]">{{ $employee->employee_number }}</div>
                                    @if($employee->alternative_employee_number)
                                        <div class="text-xs text-[var(--ui-muted)]">{{ $employee->alternative_employee_number }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($primaryContact)
                            <div class="space-y-1">
                                            <div class="font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                                                {{ $primaryContact->full_name }}
                                                @if($employee->is_active)
                                                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                                @endif
                                            </div>
                                            @if($primaryEmail)
                                                <div class="text-xs text-[var(--ui-muted)] flex items-center gap-1">
                                                    @svg('heroicon-o-envelope', 'w-3 h-3')
                                                    {{ $primaryEmail }}
                                                </div>
                                            @endif
                                            @if($primaryContact->phoneNumbers->first())
                                                <div class="text-xs text-[var(--ui-muted)] flex items-center gap-1">
                                                    @svg('heroicon-o-phone', 'w-3 h-3')
                                                    {{ $primaryContact->phoneNumbers->first()->phone_number }}
                                    </div>
                                @endif
                            </div>
                        @else
                                        <span class="text-[var(--ui-muted)] italic">Kein Kontakt verknüpft</span>
                        @endif
                                </td>
                                <td class="px-4 py-3">
                        @if($employee->employer)
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                @svg('heroicon-o-building-office', 'w-4 h-4 text-blue-600')
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm">{{ $employee->employer->display_name }}</div>
                                                @if($employee->employer->employer_number)
                                                    <div class="text-xs text-[var(--ui-muted)]">#{{ $employee->employer->employer_number }}</div>
                                                @endif
                                            </div>
                            </div>
                        @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                        @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($birthDate)
                                        <div class="flex items-center gap-1 text-sm">
                                            @svg('heroicon-o-cake', 'w-4 h-4 text-[var(--ui-muted)]')
                                            <span>{{ \Carbon\Carbon::parse($birthDate)->format('d.m.Y') }}</span>
                                        </div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            {{ \Carbon\Carbon::parse($birthDate)->age }} Jahre
                            </div>
                        @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                        @endif
                                </td>
                                <td class="px-4 py-3">
                            <div class="space-y-1">
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            {{ $employee->contracts->count() }} {{ $employee->contracts->count() === 1 ? 'Vertrag' : 'Verträge' }}
                                        </div>
                                        @if($activeContracts->count() > 0)
                                            <x-ui-badge variant="success" size="xs">{{ $activeContracts->count() }} aktiv</x-ui-badge>
                                        @endif
                                        @if($employee->contracts->where('is_active', false)->count() > 0)
                                            <x-ui-badge variant="secondary" size="xs">{{ $employee->contracts->where('is_active', false)->count() }} inaktiv</x-ui-badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                        <x-ui-badge variant="{{ $employee->is_active ? 'success' : 'secondary' }}" size="sm">
                            {{ $employee->is_active ? 'Aktiv' : 'Inaktiv' }}
                        </x-ui-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($employee->contracts->count() > 0)
                                            <a href="{{ route('hcm.contracts.show', $employee->contracts->first()) }}" 
                                               class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                               wire:navigate>
                                                @svg('heroicon-o-document-text', 'w-3 h-3') Vertrag
                                            </a>
                                        @endif
                        <x-ui-button 
                            size="sm" 
                                            variant="primary" 
                            href="{{ route('hcm.employees.show', ['employee' => $employee->id]) }}" 
                            wire:navigate
                        >
                                            @svg('heroicon-o-pencil', 'w-3 h-3')
                            Bearbeiten
                        </x-ui-button>
                                    </div>
                                </td>
                            </tr>
                            @foreach($employee->contracts as $c)
                                @php
                                    $tariffInfo = $c->tariffGroup && $c->tariffLevel 
                                        ? $c->tariffGroup->code . '/' . $c->tariffLevel->code 
                                        : null;
                                @endphp
                                <tr class="bg-[var(--ui-muted-5)]/50 hover:bg-[var(--ui-muted-5)] transition-colors">
                                    <td class="px-4 py-2">
                                        <div class="pl-6 flex items-center gap-2 text-xs text-[var(--ui-muted)]">
                                            <span>└</span>
                                            <a href="{{ route('hcm.contracts.show', $c) }}" class="hover:text-[var(--ui-secondary)] font-medium" wire:navigate>
                                                Vertrag #{{ $c->id }}
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="pl-6 space-y-2">
                                            <div class="flex items-center gap-2 text-xs">
                                                @svg('heroicon-o-calendar', 'w-3 h-3 text-[var(--ui-muted)]')
                                                <span class="font-medium">{{ optional($c->start_date)->format('d.m.Y') }}</span>
                                                <span class="text-[var(--ui-muted)]">–</span>
                                                <span class="{{ $c->end_date ? '' : 'text-green-600 font-medium' }}">
                                                    {{ optional($c->end_date)->format('d.m.Y') ?: 'unbefristet' }}
                                                </span>
                                            </div>
                                            @if($c->jobTitles && $c->jobTitles->count() > 0)
                                                <div class="flex flex-wrap items-center gap-1 text-xs">
                                                    <span class="text-[var(--ui-muted)] font-medium">Stelle:</span>
                                                    @foreach($c->jobTitles as $title)
                                                        <x-ui-badge variant="secondary" size="xs">{{ $title->name }}</x-ui-badge>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($c->jobActivities && $c->jobActivities->count() > 0)
                                                <div class="flex flex-wrap items-center gap-1 text-xs">
                                                    <span class="text-[var(--ui-muted)] font-medium">Tätigkeiten:</span>
                                                    @foreach($c->jobActivities as $activity)
                                                        <x-ui-badge variant="info" size="xs">
                                                            @if($activity->id === $c->primary_job_activity_id)
                                                                {{ $activity->code ?? '' }} {{ $c->primary_job_activity_display_name ?? $activity->name }}
                                                            @else
                                                                {{ $activity->code ?? $activity->name }}
                                                            @endif
                                                        </x-ui-badge>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2">
                                        <div class="pl-6 space-y-1 text-xs">
                                            @if($tariffInfo)
                                                <div class="flex items-center gap-1">
                                                    @svg('heroicon-o-currency-euro', 'w-3 h-3 text-blue-600')
                                                    <span class="font-medium text-blue-600">{{ $tariffInfo }}</span>
                                                </div>
                                            @endif
                                            @if($c->getEffectiveMonthlySalary() > 0)
                                                <div class="text-[var(--ui-muted)]">
                                                    {{ number_format($c->getEffectiveMonthlySalary(), 2, ',', '.') }} €/Monat
                                                </div>
                                            @endif
                                            @if($c->hours_per_month)
                                                <div class="text-[var(--ui-muted)]">
                                                    {{ $c->hours_per_month }}h/Monat
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-ui-badge variant="{{ $c->is_active ? 'success' : 'secondary' }}" size="xs">
                                            {{ $c->is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </x-ui-badge>
                                        @if($c->is_above_tariff)
                                            <x-ui-badge variant="warning" size="xs" class="mt-1 block">Übertariflich</x-ui-badge>
                                        @endif
                                        @if($c->is_temp_agency)
                                            <x-ui-badge variant="info" size="xs" class="mt-1 block">Leiharbeit</x-ui-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('hcm.contracts.show', $c) }}" 
                                           class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                           wire:navigate>
                                            @svg('heroicon-o-arrow-right', 'w-3 h-3')
                                            Details
                                        </a>
                                    </td>
                                </tr>
            @endforeach
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        @svg('heroicon-o-user-group', 'w-16 h-16 text-[var(--ui-muted)] mb-4')
                                        <div class="text-lg font-medium text-[var(--ui-secondary)] mb-1">Keine Mitarbeiter gefunden</div>
                                        <div class="text-sm text-[var(--ui-muted)]">Erstelle deinen ersten Mitarbeiter</div>
                                    </div>
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
