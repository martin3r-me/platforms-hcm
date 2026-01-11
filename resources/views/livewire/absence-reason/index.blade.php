<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Abwesenheitsgründe" icon="heroicon-o-x-circle" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <x-ui-panel title="Übersicht" subtitle="Abwesenheitsgründe verwalten">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead>
                            <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide bg-gray-50">
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Kategorie</th>
                                <th class="px-4 py-3">Attest erforderlich</th>
                                <th class="px-4 py-3">Bezahlt</th>
                                <th class="px-4 py-3">Sortierung</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ui-border)]/60">
                            @forelse($items as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-xs font-medium">{{ $item->code }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $item->name }}</td>
                                    <td class="px-4 py-3">
                                        @if($item->category)
                                            <x-ui-badge variant="secondary" size="xs">{{ $item->category }}</x-ui-badge>
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($item->requires_sick_note)
                                            @svg('heroicon-o-check-circle', 'w-4 h-4 text-green-600')
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($item->is_paid)
                                            <x-ui-badge variant="success" size="xs">Bezahlt</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="warning" size="xs">Unbezahlt</x-ui-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $item->sort_order }}</td>
                                    <td class="px-4 py-3">
                                        <x-ui-badge variant="{{ $item->is_active ? 'success' : 'secondary' }}" size="xs">
                                            {{ $item->is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </x-ui-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2">
                                            <x-ui-button variant="secondary-outline" size="xs" wire:click="openEditModal({{ $item->id }})">
                                                Bearbeiten
                                            </x-ui-button>
                                            <x-ui-button variant="danger-outline" size="xs" wire:click="delete({{ $item->id }})" 
                                                wire:confirm="Abwesenheitsgrund wirklich löschen?">
                                                Löschen
                                            </x-ui-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            @svg('heroicon-o-x-circle', 'w-16 h-16 text-[var(--ui-muted)] mb-4')
                                            <div class="text-lg font-medium text-[var(--ui-secondary)] mb-1">Keine Abwesenheitsgründe gefunden</div>
                                            <div class="text-sm text-[var(--ui-muted)]">Erstelle deinen ersten Abwesenheitsgrund</div>
                                        </div>
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
                                Neuer Abwesenheitsgrund
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

    {{-- Modals --}}
    <x-ui-modal wire:model="showCreateModal">
        <x-slot name="header">Neuen Abwesenheitsgrund anlegen</x-slot>
        <div class="space-y-4">
            <x-ui-input-text name="code" label="Code" wire:model="code" required />
            <x-ui-input-text name="name" label="Name" wire:model="name" required />
            <x-ui-input-text name="short_name" label="Kurzname" wire:model="short_name" />
            <x-ui-input-textarea name="description" label="Beschreibung" wire:model="description" />
            <x-ui-input-text name="category" label="Kategorie" wire:model="category" placeholder="z.B. sick, personal" />
            <x-ui-input-checkbox model="requires_sick_note" name="requires_sick_note" wire:model="requires_sick_note" checked-label="Attest erforderlich" unchecked-label="Kein Attest erforderlich" />
            <x-ui-input-checkbox model="is_paid" name="is_paid" wire:model="is_paid" checked-label="Bezahlt" unchecked-label="Unbezahlt" />
            <x-ui-input-number name="sort_order" label="Sortierung" wire:model="sort_order" min="0" />
            <x-ui-input-checkbox model="is_active" name="is_active" wire:model="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
        </div>
        <x-slot name="footer">
            <x-ui-button variant="secondary" wire:click="$set('showCreateModal', false)">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Erstellen</x-ui-button>
        </x-slot>
    </x-ui-modal>

    <x-ui-modal wire:model="showEditModal">
        <x-slot name="header">Abwesenheitsgrund bearbeiten</x-slot>
        <div class="space-y-4">
            <x-ui-input-text name="code" label="Code" wire:model="code" required />
            <x-ui-input-text name="name" label="Name" wire:model="name" required />
            <x-ui-input-text name="short_name" label="Kurzname" wire:model="short_name" />
            <x-ui-input-textarea name="description" label="Beschreibung" wire:model="description" />
            <x-ui-input-text name="category" label="Kategorie" wire:model="category" placeholder="z.B. sick, personal" />
            <x-ui-input-checkbox model="requires_sick_note" name="requires_sick_note" wire:model="requires_sick_note" checked-label="Attest erforderlich" unchecked-label="Kein Attest erforderlich" />
            <x-ui-input-checkbox model="is_paid" name="is_paid" wire:model="is_paid" checked-label="Bezahlt" unchecked-label="Unbezahlt" />
            <x-ui-input-number name="sort_order" label="Sortierung" wire:model="sort_order" min="0" />
            <x-ui-input-checkbox model="is_active" name="is_active" wire:model="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
        </div>
        <x-slot name="footer">
            <x-ui-button variant="secondary" wire:click="$set('showEditModal', false)">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
