<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="($employee->getContact()?->full_name ?? 'Mitarbeiter #' . $employee->employee_number)" icon="heroicon-o-user" />
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- Modern Header --}}
        @php
            $primaryContact = $employee->crmContactLinks->first()?->contact;
            $primaryEmail = $primaryContact?->emailAddresses->first()?->email_address;
            $primaryPhone = $primaryContact?->phoneNumbers->first()?->phone_number;
            $birthDate = $primaryContact?->birth_date ?? $employee->birth_date;
        @endphp
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-start justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight">
                        {{ $primaryContact?->full_name ?? 'Mitarbeiter #' . $employee->employee_number }}
                    </h1>
                    <div class="flex items-center gap-6 text-sm text-[var(--ui-muted)] flex-wrap">
                        <span class="flex items-center gap-2">
                            @svg('heroicon-o-identification', 'w-4 h-4')
                            <span class="font-medium text-[var(--ui-secondary)]">Mitarbeiternummer:</span>
                            {{ $employee->employee_number }}
                        </span>
                        @if($employee->employer)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-building-office', 'w-4 h-4')
                                {{ $employee->employer->display_name }}
                            </span>
                        @endif
                        @if($birthDate)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-cake', 'w-4 h-4')
                                {{ \Carbon\Carbon::parse($birthDate)->format('d.m.Y') }}
                                <span class="text-xs">({{ \Carbon\Carbon::parse($birthDate)->age }} Jahre)</span>
                            </span>
                        @endif
                        @if($employee->children_count && $employee->children_count > 0)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-user-group', 'w-4 h-4')
                                {{ $employee->children_count }} {{ $employee->children_count === 1 ? 'Kind' : 'Kinder' }}
                            </span>
                        @endif
                        @if($primaryEmail)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-envelope', 'w-4 h-4')
                                {{ $primaryEmail }}
                            </span>
                        @endif
                        @if($primaryPhone)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-phone', 'w-4 h-4')
                                {{ $primaryPhone }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <x-ui-badge variant="{{ $employee->is_active ? 'success' : 'secondary' }}" size="lg">
                        {{ $employee->is_active ? 'Aktiv' : 'Inaktiv' }}
                    </x-ui-badge>
                </div>
            </div>
        </div>

        {{-- Mitarbeiter-Daten --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-center gap-2 mb-6">
                @svg('heroicon-o-identification', 'w-6 h-6 text-blue-600')
                <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Mitarbeiter-Daten</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui-input-text 
                    name="employee.employee_number"
                    label="Mitarbeiternummer"
                    wire:model.live.debounce.500ms="employee.employee_number"
                    placeholder="Mitarbeiternummer eingeben..."
                    required
                    :errorKey="'employee.employee_number'"
                />

                <x-ui-input-select
                    name="employee.schooling_level"
                    label="Höchster Schulabschluss (Stelle 6)"
                    :options="[1=>'Ohne Schulabschluss',2=>'Haupt-/Volksschule',3=>'Mittlere Reife',4=>'Abitur/Fachabitur',9=>'Unbekannt']"
                    wire:model.live="employee.schooling_level"
                    placeholder="Auswahl..."
                />

                <x-ui-input-select
                    name="employee.vocational_training_level"
                    label="Höchster beruflicher Abschluss (Stelle 7)"
                    :options="[1=>'Ohne beruflichen Abschluss',2=>'Anerkannte Berufsausbildung',3=>'Meister/Techniker/Fachschule',4=>'Bachelor',5=>'Diplom/Master/Staatsexamen',6=>'Promotion',9=>'Unbekannt']"
                    wire:model.live="employee.vocational_training_level"
                    placeholder="Auswahl..."
                />
            </div>
        </div>

        <!-- Verknüpfte Kontakte -->
        <x-ui-panel title="Verknüpfte Kontakte" subtitle="CRM-Kontakte die mit diesem Mitarbeiter verknüpft sind">
            @if($employee->crmContactLinks->count() > 0)
                <div class="space-y-4">
                    @foreach($employee->crmContactLinks as $link)
                        <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-lg flex items-center justify-center">
                                    @svg('heroicon-o-user', 'w-5 h-5')
                                </div>
                                <div>
                                    <h4 class="font-medium text-[var(--ui-secondary)]">
                                        <a href="{{ route('crm.contacts.show', ['contact' => $link->contact->id]) }}" 
                                           class="hover:underline text-[var(--ui-primary)]" 
                                           wire:navigate>
                                            {{ $link->contact->full_name }}
                                        </a>
                                    </h4>
                                    @if($link->contact->emailAddresses->where('is_primary', true)->first())
                                        <p class="text-sm text-[var(--ui-muted)]">
                                            {{ $link->contact->emailAddresses->where('is_primary', true)->first()->email_address }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui-badge variant="primary" size="sm">Kontakt</x-ui-badge>
                                <x-ui-button 
                                    size="sm" 
                                    variant="danger-outline" 
                                    wire:click="unlinkContact({{ $link->contact->id }})"
                                    wire:confirm="Kontakt-Verknüpfung wirklich entfernen?"
                                >
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </x-ui-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    @svg('heroicon-o-user', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-4')
                    <h4 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Keine Kontakte verknüpft</h4>
                    <p class="text-[var(--ui-muted)] mb-4">Dieser Mitarbeiter hat noch keine CRM-Kontakte.</p>
                    <div class="flex gap-3 justify-center">
                        <x-ui-button variant="secondary" wire:click="linkContact">
                            @svg('heroicon-o-link', 'w-4 h-4')
                            Kontakt verknüpfen
                        </x-ui-button>
                        <x-ui-button variant="secondary" wire:click="addContact">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Neuen Kontakt erstellen
                        </x-ui-button>
                    </div>
                </div>
            @endif
        </x-ui-panel>

        {{-- Verträge --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-document-text', 'w-6 h-6 text-indigo-600')
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Verträge</h2>
                </div>
                <x-ui-button variant="primary" size="sm" wire:click="addContract">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neuer Vertrag
                </x-ui-button>
            </div>
            
            @if($employee->contracts->count() > 0)
                <div class="space-y-6">
                    @foreach($employee->contracts as $contract)
                        @php
                            $tariffInfo = $contract->tariffGroup && $contract->tariffLevel 
                                ? $contract->tariffGroup->code . '/' . $contract->tariffLevel->code 
                                : null;
                            $isActive = !$contract->end_date || \Carbon\Carbon::parse($contract->end_date)->isFuture();
                            $isExpired = $contract->end_date && \Carbon\Carbon::parse($contract->end_date)->isPast();
                        @endphp
                        <div class="border border-[var(--ui-border)]/60 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                            {{-- Vertrag Header --}}
                            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 p-6 border-b border-[var(--ui-border)]/60">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="text-xl font-bold text-[var(--ui-secondary)]">
                                                <a href="{{ route('hcm.contracts.show', $contract) }}" 
                                                   class="hover:text-indigo-600 transition-colors"
                                                   wire:navigate>
                                                    Vertrag #{{ $contract->id }}
                                                </a>
                                            </h3>
                                            @if($isExpired)
                                                <x-ui-badge variant="danger" size="sm">Beendet</x-ui-badge>
                                            @elseif($contract->end_date && !$isExpired)
                                                <x-ui-badge variant="warning" size="sm">Läuft aus</x-ui-badge>
                                            @else
                                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                                            @endif
                                            @if($contract->is_above_tariff)
                                                <x-ui-badge variant="warning" size="sm">Übertariflich</x-ui-badge>
                                            @endif
                                            @if($contract->is_temp_agency)
                                                <x-ui-badge variant="info" size="sm">Leiharbeit</x-ui-badge>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-6 text-sm text-[var(--ui-muted)] flex-wrap">
                                            @if($contract->start_date)
                                                <span class="flex items-center gap-1">
                                                    @svg('heroicon-o-calendar-days', 'w-4 h-4')
                                                    Start: {{ $contract->start_date->format('d.m.Y') }}
                                                </span>
                                            @endif
                                            @if($contract->end_date)
                                                <span class="flex items-center gap-1">
                                                    @svg('heroicon-o-calendar-days', 'w-4 h-4')
                                                    Ende: {{ $contract->end_date->format('d.m.Y') }}
                                                </span>
                                            @else
                                                <span class="flex items-center gap-1 text-green-600 font-medium">
                                                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                                                    Unbefristet
                                                </span>
                                            @endif
                                            @if($contract->hours_per_month)
                                                <span class="flex items-center gap-1">
                                                    @svg('heroicon-o-clock', 'w-4 h-4')
                                                    {{ $contract->hours_per_month }}h/Monat
                                                </span>
                                            @endif
                                            @if($tariffInfo)
                                                <span class="flex items-center gap-1 text-blue-600 font-medium">
                                                    @svg('heroicon-o-currency-euro', 'w-4 h-4')
                                                    Tarif: {{ $tariffInfo }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-ui-button 
                                            size="sm" 
                                            variant="primary-outline" 
                                            :href="route('hcm.contracts.show', $contract)"
                                            wire:navigate
                                        >
                                            @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                            Details
                                        </x-ui-button>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Vertrag Details --}}
                            <div class="p-6 space-y-4">
                                {{-- Stellen & Tätigkeiten --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @if($contract->jobTitles->count() > 0)
                                        <div>
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] uppercase mb-2">Stellenbezeichnungen</div>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($contract->jobTitles as $title)
                                                    <x-ui-badge variant="secondary" size="sm">{{ $title->name }}</x-ui-badge>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    @if($contract->jobActivities->count() > 0)
                                        <div>
                                            <div class="text-xs font-semibold text-[var(--ui-muted)] uppercase mb-2">Tätigkeiten</div>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($contract->jobActivities as $activity)
                                                    <x-ui-badge variant="info" size="sm">
                                                        {{ $activity->code ?? '' }} 
                                                        @if($activity->id === $contract->primary_job_activity_id)
                                                            {{ $contract->primary_job_activity_display_name ?? $activity->name }}
                                                        @else
                                                            {{ $activity->name }}
                                                        @endif
                                                    </x-ui-badge>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                
                                {{-- Vergütungs-Übersicht --}}
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-[var(--ui-border)]/60">
                                    {{-- Gesamtgehalt --}}
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <div class="text-xs font-medium text-green-700 mb-1">Gesamtgehalt</div>
                                        <div class="text-2xl font-bold text-green-600">
                                            {{ number_format($contract->getEffectiveMonthlySalary(), 2, ',', '.') }} €
                                        </div>
                                        <div class="text-xs text-green-600">effektiv/Monat</div>
                                    </div>
                                    
                                    {{-- Tarifsatz --}}
                                    @if($contract->getCurrentTariffRate())
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <div class="text-xs font-medium text-blue-700 mb-1">Tarifsatz</div>
                                            <div class="text-xl font-bold text-blue-600">
                                                {{ number_format((float)$contract->getCurrentTariffRate()->amount, 2, ',', '.') }} €
                                            </div>
                                            <div class="text-xs text-blue-600">Grundgehalt</div>
                                        </div>
                                    @endif
                                    
                                    {{-- Übertariflich --}}
                                    @if($contract->is_above_tariff && $contract->above_tariff_amount)
                                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                            <div class="text-xs font-medium text-purple-700 mb-1">Übertariflich</div>
                                            <div class="text-xl font-bold text-purple-600">
                                                +{{ number_format((float)$contract->above_tariff_amount, 2, ',', '.') }} €
                                            </div>
                                            <div class="text-xs text-purple-600">Zulage</div>
                                        </div>
                                    @endif
                                    
                                    {{-- Urlaub --}}
                                    @if($contract->vacation_entitlement !== null)
                                        <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                                            <div class="text-xs font-medium text-teal-700 mb-1">Urlaub</div>
                                            <div class="text-xl font-bold text-teal-600">
                                                {{ $contract->vacation_entitlement }} Tage
                                            </div>
                                            @if($contract->vacation_taken !== null)
                                                <div class="text-xs text-teal-600">
                                                    {{ $contract->vacation_taken }} genommen
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                
                                {{-- Zusatzinfos --}}
                                <div class="flex flex-wrap gap-2 pt-2 text-xs">
                                    @if($contract->probation_end_date)
                                        <span class="px-2 py-1 bg-purple-50 text-purple-700 rounded">Probezeit bis {{ $contract->probation_end_date->format('d.m.Y') }}</span>
                                    @endif
                                    @if($contract->company_car_enabled)
                                        <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded flex items-center gap-1">
                                            @svg('heroicon-o-truck', 'w-3 h-3') Dienstwagen
                                        </span>
                                    @endif
                                    @if($contract->cost_center_id)
                                        <span class="px-2 py-1 bg-gray-50 text-gray-700 rounded">Kostenstelle: {{ optional($contract->costCenter)->name ?? $contract->cost_center }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="flex flex-col items-center justify-center">
                        @svg('heroicon-o-document-text', 'w-16 h-16 text-[var(--ui-muted)] mb-4')
                        <div class="text-lg font-medium text-[var(--ui-secondary)] mb-1">Keine Verträge vorhanden</div>
                        <div class="text-sm text-[var(--ui-muted)] mb-4">Dieser Mitarbeiter hat noch keine Arbeitsverträge.</div>
                        <x-ui-button variant="primary" wire:click="addContract">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Neuen Vertrag erstellen
                        </x-ui-button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Arbeitgeber-Informationen -->
        <x-ui-panel title="Arbeitgeber" subtitle="Zugewiesener Arbeitgeber und Vertragshinweise">
            @if($employee->employer)
                <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-[var(--ui-secondary)] text-[var(--ui-on-secondary)] rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-building-office', 'w-5 h-5')
                        </div>
                        <div>
                            <h4 class="font-medium text-[var(--ui-secondary)]">
                                <a href="{{ route('hcm.employers.show', ['employer' => $employee->employer->id]) }}" 
                                   class="hover:underline text-[var(--ui-primary)]" 
                                   wire:navigate>
                                    {{ $employee->employer->display_name }}
                                </a>
                            </h4>
                            <p class="text-sm text-[var(--ui-muted)]">
                                Arbeitgeber-Nummer: {{ $employee->employer->employer_number }}
                            </p>
                        </div>
                    </div>
                    <x-ui-badge variant="secondary" size="sm">Arbeitgeber</x-ui-badge>
                </div>
                
                <!-- Vertragshinweise -->
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-document-text', 'w-5 h-5 text-blue-600')
                        <h4 class="font-medium text-blue-900">Vertragsverwaltung</h4>
                    </div>
                    <p class="text-blue-700 text-sm mb-3">
                        Für detaillierte Vertragsverwaltung (Start-/Enddatum, Arbeitszeit, Steuerklasse, etc.) 
                        können Verträge im HCM-System verwaltet werden.
                    </p>
                    <div class="text-xs text-blue-600">
                        <strong>Verfügbare Vertragsfelder:</strong> Start-/Enddatum, Vertragstyp, Beschäftigungsstatus, 
                        Arbeitszeit pro Monat, Jahresurlaub, Steuerklasse, Sozialversicherungsnummer
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    @svg('heroicon-o-building-office', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-4')
                    <h4 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Kein Arbeitgeber zugewiesen</h4>
                    <p class="text-[var(--ui-muted)]">Dieser Mitarbeiter ist noch keinem Arbeitgeber zugewiesen.</p>
                </div>
            @endif
        </x-ui-panel>

    <!-- Contact Link Modal -->
    <x-ui-modal
        size="sm"
        model="contactLinkModalShow"
    >
        <x-slot name="header">
            Kontakt verknüpfen
        </x-slot>

        <div class="space-y-4">
            <x-ui-input-select
                name="contactLinkForm.contact_id"
                label="Kontakt auswählen"
                :options="$availableContacts"
                optionValue="id"
                optionLabel="full_name"
                :nullable="true"
                nullLabel="– Kontakt auswählen –"
                wire:model.live="contactLinkForm.contact_id"
                required
            />
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeContactLinkModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveContactLink">
                    Verknüpfen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    <!-- Company Selection Modal -->
    <x-ui-modal
        size="sm"
        model="companySelectionModalShow"
    >
        <x-slot name="header">
            Unternehmen auswählen
        </x-slot>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900">Hinweis</h4>
                </div>
                <p class="text-blue-700 text-sm">
                    Der Kontakt <strong>{{ $selectedContactForCompanySelection?->full_name }}</strong> ist mit mehreren Unternehmen verknüpft. 
                    Bitte wählen Sie das Unternehmen aus, für das dieser Mitarbeiter tätig ist.
                </p>
            </div>

            <x-ui-input-select
                name="companySelectionForm.company_id"
                label="Unternehmen auswählen"
                :options="$availableCompaniesForSelection"
                optionValue="company.id"
                optionLabel="company.display_name"
                :nullable="true"
                nullLabel="– Unternehmen auswählen –"
                wire:model.live="companySelectionForm.company_id"
                required
            />

            <div class="text-sm text-muted">
                <p><strong>Verfügbare Unternehmen:</strong></p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach($availableCompaniesForSelection as $relation)
                        <li>{{ $relation->company->display_name }}
                            @if($relation->position)
                                <span class="text-muted">({{ $relation->position }})</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeCompanySelectionModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveCompanySelection">
                    Verknüpfen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    <!-- Contact Create Modal -->
    <x-ui-modal
        size="lg"
        model="contactCreateModalShow"
    >
        <x-slot name="header">
            Neuen Kontakt erstellen
        </x-slot>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900">Hinweis</h4>
                </div>
                <p class="text-blue-700 text-sm">Der neue Kontakt wird automatisch mit diesem Mitarbeiter verknüpft.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text
                    name="contactForm.first_name"
                    label="Vorname"
                    wire:model.live="contactForm.first_name"
                    required
                    placeholder="Vorname eingeben"
                />
                
                <x-ui-input-text
                    name="contactForm.last_name"
                    label="Nachname"
                    wire:model.live="contactForm.last_name"
                    required
                    placeholder="Nachname eingeben"
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text
                    name="contactForm.middle_name"
                    label="Zweiter Vorname"
                    wire:model.live="contactForm.middle_name"
                    placeholder="Zweiter Vorname (optional)"
                />
                
                <x-ui-input-text
                    name="contactForm.nickname"
                    label="Spitzname"
                    wire:model.live="contactForm.nickname"
                    placeholder="Spitzname (optional)"
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-date
                    name="contactForm.birth_date"
                    label="Geburtsdatum"
                    wire:model.live="contactForm.birth_date"
                    placeholder="Geburtsdatum (optional)"
                    :nullable="true"
                />
            </div>

            <x-ui-input-textarea
                name="contactForm.notes"
                label="Notizen"
                wire:model.live="contactForm.notes"
                placeholder="Zusätzliche Notizen (optional)"
                rows="3"
            />
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeContactCreateModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveContact">
                    Kontakt erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        @if($this->isDirty)
                            <x-ui-button variant="primary" size="sm" wire:click="save" class="w-full">
                                <span class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-check', 'w-4 h-4')
                                    Änderungen speichern
                                </span>
                            </x-ui-button>
                        @endif
                        <x-ui-button variant="primary" size="sm" wire:click="addContract" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-document-plus', 'w-4 h-4')
                                Neuer Vertrag
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="linkContact" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-link', 'w-4 h-4')
                                Kontakt verknüpfen
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="addContact" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-user-plus', 'w-4 h-4')
                                Kontakt erstellen
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Übersichten --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Übersichten</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('hcm.employees.benefits.index', $employee)" wire:navigate class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-gift', 'w-4 h-4')
                                Benefits
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('hcm.employees.issues.index', $employee)" wire:navigate class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-archive-box', 'w-4 h-4')
                                Ausgaben
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('hcm.employees.trainings.index', $employee)" wire:navigate class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-academic-cap', 'w-4 h-4')
                                Schulungen
                            </span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-3 text-sm">
                <div class="text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
