<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $employee->employee_number }}" icon="heroicon-o-user">
            <div class="flex items-center gap-2">
                <a href="{{ route('hcm.employees.index') }}" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]" wire:navigate>
                    ← Mitarbeiter
                </a>
                @if($this->isDirty)
                    <x-ui-button 
                        variant="primary" 
                        size="sm"
                        wire:click="save"
                    >
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </x-ui-button>
                @endif
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <!-- Mitarbeiter-Daten -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">Mitarbeiter-Daten</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui-input-text 
                    name="employee.employee_number"
                    label="Mitarbeiternummer"
                    wire:model.live.debounce.500ms="employee.employee_number"
                    placeholder="Mitarbeiternummer eingeben..."
                    required
                    :errorKey="'employee.employee_number'"
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
        <x-ui-page-sidebar title="Aktionen" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-3">
                <x-ui-button variant="secondary" size="sm" wire:click="linkContact" class="w-full justify-start">
                    @svg('heroicon-o-link', 'w-4 h-4')
                    <span class="ml-2">Kontakt verknüpfen</span>
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" wire:click="addContact" class="w-full justify-start">
                    @svg('heroicon-o-user-plus', 'w-4 h-4')
                    <span class="ml-2">Kontakt erstellen</span>
                </x-ui-button>
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
