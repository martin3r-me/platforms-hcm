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

        <!-- Haupt-Content (nimmt Restplatz, scrollt) -->
        <div class="flex-grow-1 overflow-y-auto p-4">
            
            {{-- Mitarbeiter-Daten --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Mitarbeiter-Daten</h3>
                <div class="grid grid-cols-2 gap-4">
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
        </div>

        <!-- Aktivitäten (immer unten) -->
        <div x-data="{ open: false }" class="flex-shrink-0 border-t border-muted">
            <div 
                @click="open = !open" 
                class="cursor-pointer border-top-1 border-top-solid border-top-muted border-bottom-1 border-bottom-solid border-bottom-muted p-2 text-center d-flex items-center justify-center gap-1 mx-2 shadow-lg"
            >
                AKTIVITÄTEN 
                <span class="text-xs">
                    {{$employee->activities->count()}}
                </span>
                <x-heroicon-o-chevron-double-down 
                    class="w-3 h-3" 
                    x-show="!open"
                />
                <x-heroicon-o-chevron-double-up 
                    class="w-3 h-3" 
                    x-show="open"
                />
            </div>
            <div x-show="open" class="p-2 max-h-xs overflow-y-auto">
                <livewire:activity-log.index
                    :model="$employee"
                    :key="get_class($employee) . '_' . $employee->id"
                />
            </div>
        </div>
    </div>

    <!-- Rechte Spalte -->
    <div class="min-w-80 w-80 d-flex flex-col border-left-1 border-left-solid border-left-muted">

        <div class="d-flex gap-2 border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <x-heroicon-o-cog-6-tooth class="w-6 h-6"/>
            Einstellungen
        </div>
        <div class="flex-grow-1 overflow-y-auto p-4">

            {{-- Navigation Buttons --}}
            <div class="d-flex flex-col gap-2 mb-4">
                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    :href="route('hcm.employees.index')" 
                    wire:navigate
                    class="w-full"
                >
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-left', 'w-4 h-4')
                        Zurück zu Mitarbeitern
                    </div>
                </x-ui-button>
            </div>

            {{-- Kurze Übersicht --}}
            <div class="mb-4 p-3 bg-muted-5 rounded-lg">
                <h4 class="font-semibold mb-2 text-secondary">Mitarbeiter-Übersicht</h4>
                <div class="space-y-1 text-sm">
                    <div><strong>Mitarbeiternummer:</strong> {{ $employee->employee_number }}</div>
                    <div><strong>Arbeitgeber:</strong> 
                        @if($employee->employer)
                            <a href="{{ route('hcm.employers.show', ['employer' => $employee->employer->id]) }}" 
                               class="hover:underline text-primary" 
                               wire:navigate>
                                {{ $employee->employer->display_name }}
                            </a>
                        @else
                            <span class="text-muted">Nicht zugewiesen</span>
                        @endif
                    </div>
                    <div><strong>Status:</strong> 
                        <x-ui-badge variant="{{ $employee->is_active ? 'success' : 'secondary' }}" size="xs">
                            {{ $employee->is_active ? 'Aktiv' : 'Inaktiv' }}
                        </x-ui-badge>
                    </div>
                    <div><strong>Verknüpfte Kontakte:</strong> {{ $employee->crmContactLinks->count() }}</div>
                </div>
            </div>

            <hr>

            {{-- Verknüpfte Kontakte --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Verknüpfte Kontakte</h4>
                <div class="space-y-2">
                                @foreach($employee->crmContactLinks as $link)
                        <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded cursor-pointer" wire:click="editContact({{ $link->contact->id }})">
                            <div class="flex-grow-1">
                                <div class="text-sm font-medium">
                                    <a href="{{ route('crm.contacts.show', ['contact' => $link->contact->id]) }}" 
                                       class="hover:underline text-primary" 
                                       wire:navigate
                                       @click.stop>
                                        {{ $link->contact->full_name }}
                                    </a>
                                </div>
                                <div class="text-xs text-muted">
                                    @if($link->contact->emailAddresses->where('is_primary', true)->first())
                                        {{ $link->contact->emailAddresses->where('is_primary', true)->first()->email_address }}
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <x-ui-badge variant="primary" size="xs">Kontakt</x-ui-badge>
                                <x-ui-button 
                                    size="xs" 
                                    variant="danger-outline" 
                                    wire:click="unlinkContact({{ $link->contact->id }})"
                                    wire:confirm="Kontakt-Verknüpfung wirklich entfernen?"
                                    @click.stop
                                >
                                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                                </x-ui-button>
                            </div>
                        </div>
                    @endforeach
                    @if($employee->crmContactLinks->count() === 0)
                        <p class="text-sm text-muted">Noch keine Kontakte verknüpft.</p>
                        <div class="d-flex flex-col gap-2">
                            <x-ui-button size="sm" variant="secondary-outline" wire:click="linkContact">
                                <div class="d-flex items-center gap-2">
                                    @svg('heroicon-o-link', 'w-4 h-4')
                                    Kontakt verknüpfen
                                </div>
                            </x-ui-button>
                            <x-ui-button size="sm" variant="secondary-outline" wire:click="addContact">
                                <div class="d-flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Neuen Kontakt erstellen
                                </div>
                            </x-ui-button>
                        </div>
                    @endif
                </div>
            </div>

            <hr>

            {{-- Arbeitgeber --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Arbeitgeber</h4>
                <div class="space-y-2">
                    @if($employee->employer)
                        <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded">
                            <div class="flex-grow-1">
                                <div class="text-sm font-medium">
                                    <a href="{{ route('hcm.employers.show', ['employer' => $employee->employer->id]) }}" 
                                       class="hover:underline text-primary" 
                                       wire:navigate
                                       @click.stop>
                                        {{ $employee->employer->display_name }}
                                    </a>
                                </div>
                                <div class="text-xs text-muted">
                                    Arbeitgeber-Nummer: {{ $employee->employer->employer_number }}
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <x-ui-badge variant="primary" size="xs">Arbeitgeber</x-ui-badge>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-muted">Kein Arbeitgeber zugewiesen.</p>
                    @endif
                    @if($employee->linkedCompanies()->count() === 0)
                        <p class="text-sm text-muted">Noch keine Unternehmen verknüpft.</p>
                    @endif
                </div>
            </div>

        </div>
    </div>

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
</x-ui-page>
