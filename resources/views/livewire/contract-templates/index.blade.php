<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Vertragsvorlagen" icon="heroicon-o-document-duplicate" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <x-ui-panel title="Übersicht" subtitle="Vertragsvorlagen verwalten">
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
                                <th class="px-4 py-2">Unterschrift</th>
                                <th class="px-4 py-2">Verträge</th>
                                <th class="px-4 py-2">Sortierung</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ui-border)]/60">
                            @forelse($items as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $item->code ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <div>
                                            <div class="font-medium">{{ $item->name }}</div>
                                            @if($item->description)
                                                <div class="text-xs text-[var(--ui-muted)]">{{ Str::limit($item->description, 50) }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($item->requires_signature)
                                            <span class="text-green-600 flex items-center gap-1">
                                                @svg('heroicon-o-check-circle', 'w-4 h-4')
                                                <span class="text-xs">Ja</span>
                                            </span>
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">
                                            {{ $item->onboarding_contracts_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">{{ $item->sort_order }}</td>
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
                                    <td colspan="7" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                        @svg('heroicon-o-document-duplicate', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                        <div class="text-sm">Keine Vertragsvorlagen gefunden</div>
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
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="primary" size="sm" class="w-full" wire:click="openCreateModal">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neue Vertragsvorlage
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Gesamt</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $items->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Aktiv</span>
                            <span class="font-semibold text-green-600">{{ $items->where('is_active', true)->count() }}</span>
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Vertragsvorlagen-Übersicht geladen</div>
                        <div class="text-[var(--ui-muted)]">{{ now()->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Modals --}}
    <x-ui-modal wire:model="showCreateModal">
        <x-slot name="header">Neue Vertragsvorlage anlegen</x-slot>
        <div class="space-y-4">
            <x-ui-input-text name="name" label="Name *" wire:model="name" required />
            <x-ui-input-text name="code" label="Code" wire:model="code" />
            <x-ui-input-textarea name="description" label="Beschreibung" wire:model="description" />
            <x-ui-input-textarea name="content" label="Vertragstext" wire:model="content" rows="6" />
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="sort_order" label="Sortierung" wire:model="sort_order" type="number" />
                <div class="space-y-2">
                    <x-ui-input-checkbox model="requires_signature" name="requires_signature" wire:model="requires_signature" checked-label="Unterschrift erforderlich" unchecked-label="Keine Unterschrift" />
                    <x-ui-input-checkbox model="is_active" name="is_active" wire:model="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
                </div>
            </div>
        </div>
        <x-slot name="footer">
            <x-ui-button variant="secondary" wire:click="closeModals">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>

    <x-ui-modal wire:model="showEditModal">
        <x-slot name="header">Vertragsvorlage bearbeiten</x-slot>
        <div class="space-y-4">
            <x-ui-input-text name="name" label="Name *" wire:model="name" required />
            <x-ui-input-text name="code" label="Code" wire:model="code" />
            <x-ui-input-textarea name="description" label="Beschreibung" wire:model="description" />
            <x-ui-input-textarea name="content" label="Vertragstext" wire:model="content" rows="6" />
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="sort_order" label="Sortierung" wire:model="sort_order" type="number" />
                <div class="space-y-2">
                    <x-ui-input-checkbox model="requires_signature" name="requires_signature" wire:model="requires_signature" checked-label="Unterschrift erforderlich" unchecked-label="Keine Unterschrift" />
                    <x-ui-input-checkbox model="is_active" name="is_active" wire:model="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
                </div>
            </div>
        </div>
        <x-slot name="footer">
            <x-ui-button variant="secondary" wire:click="closeModals">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
