<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Tarifklassen" icon="heroicon-o-scale">
            <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Übersicht" subtitle="Verfügbare Tarifklassen (Steuer)">
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neu
                </x-ui-button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-2">
                                <button wire:click="sortBy('code')" class="flex items-center gap-1 hover:text-[var(--ui-secondary)]">
                                    Code
                                </button>
                            </th>
                            <th class="px-4 py-2">Bezeichnung</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($taxClasses as $tax)
                            <tr>
                                <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $tax->code }}</td>
                                <td class="px-4 py-2">{{ $tax->name }}</td>
                                <td class="px-4 py-2">
                                    <x-ui-badge variant="{{ $tax->is_active ? 'success' : 'secondary' }}" size="xs">
                                        {{ $tax->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center">
                                    @svg('heroicon-o-scale', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                    <div class="text-sm text-[var(--ui-muted)]">Keine Tarifklassen gefunden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $taxClasses->links() }}
            </div>
        </x-ui-panel>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Hinweise" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-3 text-sm">
                <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                    Tarifklassen werden in Verträgen verwendet (z. B. Steuerklasse).
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktionen" width="w-80" :defaultOpen="false" storeKey="tariffActivityOpen" side="right">
            <div class="p-6">
                <x-ui-button variant="secondary" size="sm" wire:click="openCreateModal" class="w-full justify-start">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neue Tarifklasse
                </x-ui-button>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-modal wire:model="modalShow" size="md">
        <x-slot name="header">Neue Tarifklasse</x-ui-slot>
        <div class="space-y-4">
            <x-ui-input-text name="form.code" label="Code" wire:model.live="form.code" required />
            <x-ui-input-text name="form.name" label="Bezeichnung" wire:model.live="form.name" required />
            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_active" wire:model.live="form.is_active" class="w-4 h-4" />
                <label for="is_active" class="text-sm">Aktiv</label>
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeCreateModal">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="save">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>


