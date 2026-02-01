<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Ausgabe-Typen" icon="heroicon-o-archive-box" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <x-ui-panel title="Übersicht" subtitle="Ausgabe-Typen verwalten">
                <div class="flex justify-between items-center mb-4">
                    <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" class="max-w-xs" />
                    <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                        @svg('heroicon-o-plus', 'w-4 h-4') Neu
                    </x-ui-button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead>
                            <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2">Code</th>
                                <th class="px-4 py-2">Name</th>
                                <th class="px-4 py-2">Kategorie</th>
                                <th class="px-4 py-2">Rückgabe</th>
                                <th class="px-4 py-2">Felder</th>
                                <th class="px-4 py-2">Unterschrift</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ui-border)]/60">
                            @forelse($items as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $item->code }}</td>
                                    <td class="px-4 py-2">
                                        <div class="font-medium">{{ $item->name }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($item->category)
                                            <x-ui-badge variant="secondary" size="xs">{{ $item->category }}</x-ui-badge>
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-ui-badge variant="{{ $item->requires_return ? 'warning' : 'secondary' }}" size="xs">
                                            {{ $item->requires_return ? 'Erforderlich' : 'Optional' }}
                                        </x-ui-badge>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($item->field_definitions && count($item->field_definitions) > 0)
                                            <x-ui-badge variant="info" size="xs">{{ count($item->field_definitions) }} Felder</x-ui-badge>
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($item->requires_signature)
                                            <x-ui-badge variant="info" size="xs">Ja</x-ui-badge>
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-ui-badge variant="{{ $item->is_active ? 'success' : 'secondary' }}" size="xs">
                                            {{ $item->is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </x-ui-badge>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex gap-2">
                                            <x-ui-button variant="secondary-outline" size="xs" wire:click="openEditModal({{ $item->id }})">
                                                Bearbeiten
                                            </x-ui-button>
                                            <x-ui-button variant="danger-outline" size="xs" wire:click="delete({{ $item->id }})">
                                                Löschen
                                            </x-ui-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                        @svg('heroicon-o-archive-box', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                        <div class="text-sm">Keine Ausgabe-Typen gefunden</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui-panel>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="primary" size="sm" class="w-full" wire:click="openCreateModal">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neuer Ausgabe-Typ
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Gesamt</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $items->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Aktiv</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $items->where('is_active', true)->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Ausgabe-Typen-Übersicht geladen</div>
                        <div class="text-[var(--ui-muted)]">{{ now()->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Create Modal --}}
    <x-ui-modal wire:model="showCreateModal" size="lg">
        <x-slot name="header">Neuen Ausgabe-Typ anlegen</x-slot>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="code" label="Code *" wire:model="code" required />
                <x-ui-input-text name="name" label="Name *" wire:model="name" required />
            </div>
            <x-ui-input-text name="category" label="Kategorie" wire:model="category" placeholder="z.B. IT, Kleidung, Schlüssel" />
            
            <div class="grid grid-cols-3 gap-4">
                <x-ui-input-checkbox model="requires_return" name="requires_return" wire:model="requires_return" checked-label="Rückgabe erforderlich" unchecked-label="Rückgabe optional" />
                <x-ui-input-checkbox model="requires_signature" name="requires_signature" wire:model="requires_signature" checked-label="Unterschrift erforderlich" unchecked-label="Keine Unterschrift" />
                <x-ui-input-checkbox model="is_active" name="is_active" wire:model="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
            </div>

            {{-- Felddefinitionen --}}
            <div class="border-t border-[var(--ui-border)] pt-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Felddefinitionen</h3>
                    <x-ui-button variant="secondary-outline" size="xs" wire:click="addFieldDefinition">
                        @svg('heroicon-o-plus', 'w-4 h-4') Feld hinzufügen
                    </x-ui-button>
                </div>

                @if(count($field_definitions) > 0)
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @foreach($field_definitions as $index => $field)
                            <div class="p-3 border border-[var(--ui-border)] rounded-lg space-y-2">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 grid grid-cols-2 gap-2">
                                        <x-ui-input-text
                                            :name="'field_definitions.' . $index . '.key'"
                                            label="Key"
                                            wire:model="field_definitions.{{ $index }}.key"
                                            placeholder="z.B. laptop_type"
                                        />
                                        <x-ui-input-select
                                            :name="'field_definitions.' . $index . '.type'"
                                            label="Typ"
                                            wire:model="field_definitions.{{ $index }}.type"
                                            :options="[
                                                ['value' => 'text', 'label' => 'Text'],
                                                ['value' => 'textarea', 'label' => 'Textarea'],
                                                ['value' => 'select', 'label' => 'Select'],
                                                ['value' => 'checkbox', 'label' => 'Checkbox'],
                                                ['value' => 'date', 'label' => 'Datum'],
                                            ]"
                                            option-value="value"
                                            option-label="label"
                                        />
                                    </div>
                                    <x-ui-button variant="danger-outline" size="xs" wire:click="removeFieldDefinition({{ $index }})" class="ml-2">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-button>
                                </div>
                                <x-ui-input-text
                                    :name="'field_definitions.' . $index . '.label'"
                                    label="Label"
                                    wire:model="field_definitions.{{ $index }}.label"
                                    placeholder="z.B. Laptop-Typ"
                                />
                                <x-ui-input-text
                                    :name="'field_definitions.' . $index . '.placeholder'"
                                    label="Placeholder"
                                    wire:model="field_definitions.{{ $index }}.placeholder"
                                    placeholder="z.B. E15 Gen 4"
                                />
                                <x-ui-input-checkbox
                                    :model="'field_definitions.' . $index . '.required'"
                                    :name="'field_definitions.' . $index . '.required'"
                                    wire:model="field_definitions.{{ $index }}.required"
                                    checked-label="Pflichtfeld"
                                    unchecked-label="Optional"
                                />
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-[var(--ui-muted)] text-center py-4 border border-dashed border-[var(--ui-border)] rounded-lg">
                        Noch keine Felder definiert. Klicke auf "Feld hinzufügen" um Felder zu erstellen.
                    </div>
                @endif
            </div>
        </div>
        <x-slot name="footer">
            <x-ui-button variant="secondary" wire:click="closeModals">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>

    {{-- Edit Modal --}}
    <x-ui-modal wire:model="showEditModal" size="lg">
        <x-slot name="header">Ausgabe-Typ bearbeiten</x-slot>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="code" label="Code *" wire:model="code" required />
                <x-ui-input-text name="name" label="Name *" wire:model="name" required />
            </div>
            <x-ui-input-text name="category" label="Kategorie" wire:model="category" placeholder="z.B. IT, Kleidung, Schlüssel" />
            
            <div class="grid grid-cols-3 gap-4">
                <x-ui-input-checkbox model="requires_return" name="requires_return" wire:model="requires_return" checked-label="Rückgabe erforderlich" unchecked-label="Rückgabe optional" />
                <x-ui-input-checkbox model="requires_signature" name="requires_signature" wire:model="requires_signature" checked-label="Unterschrift erforderlich" unchecked-label="Keine Unterschrift" />
                <x-ui-input-checkbox model="is_active" name="is_active" wire:model="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
            </div>

            {{-- Felddefinitionen --}}
            <div class="border-t border-[var(--ui-border)] pt-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Felddefinitionen</h3>
                    <x-ui-button variant="secondary-outline" size="xs" wire:click="addFieldDefinition">
                        @svg('heroicon-o-plus', 'w-4 h-4') Feld hinzufügen
                    </x-ui-button>
                </div>

                @if(count($field_definitions) > 0)
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @foreach($field_definitions as $index => $field)
                            <div class="p-3 border border-[var(--ui-border)] rounded-lg space-y-2">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 grid grid-cols-2 gap-2">
                                        <x-ui-input-text
                                            :name="'field_definitions.' . $index . '.key'"
                                            label="Key"
                                            wire:model="field_definitions.{{ $index }}.key"
                                            placeholder="z.B. laptop_type"
                                        />
                                        <x-ui-input-select
                                            :name="'field_definitions.' . $index . '.type'"
                                            label="Typ"
                                            wire:model="field_definitions.{{ $index }}.type"
                                            :options="[
                                                ['value' => 'text', 'label' => 'Text'],
                                                ['value' => 'textarea', 'label' => 'Textarea'],
                                                ['value' => 'select', 'label' => 'Select'],
                                                ['value' => 'checkbox', 'label' => 'Checkbox'],
                                                ['value' => 'date', 'label' => 'Datum'],
                                            ]"
                                            option-value="value"
                                            option-label="label"
                                        />
                                    </div>
                                    <x-ui-button variant="danger-outline" size="xs" wire:click="removeFieldDefinition({{ $index }})" class="ml-2">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-ui-button>
                                </div>
                                <x-ui-input-text
                                    :name="'field_definitions.' . $index . '.label'"
                                    label="Label"
                                    wire:model="field_definitions.{{ $index }}.label"
                                    placeholder="z.B. Laptop-Typ"
                                />
                                <x-ui-input-text
                                    :name="'field_definitions.' . $index . '.placeholder'"
                                    label="Placeholder"
                                    wire:model="field_definitions.{{ $index }}.placeholder"
                                    placeholder="z.B. E15 Gen 4"
                                />
                                <x-ui-input-checkbox
                                    :model="'field_definitions.' . $index . '.required'"
                                    :name="'field_definitions.' . $index . '.required'"
                                    wire:model="field_definitions.{{ $index }}.required"
                                    checked-label="Pflichtfeld"
                                    unchecked-label="Optional"
                                />
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-[var(--ui-muted)] text-center py-4 border border-dashed border-[var(--ui-border)] rounded-lg">
                        Noch keine Felder definiert. Klicke auf "Feld hinzufügen" um Felder zu erstellen.
                    </div>
                @endif
            </div>
        </div>
        <x-slot name="footer">
            <x-ui-button variant="secondary" wire:click="closeModals">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
