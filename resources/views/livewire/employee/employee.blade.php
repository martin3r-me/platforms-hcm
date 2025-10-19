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
