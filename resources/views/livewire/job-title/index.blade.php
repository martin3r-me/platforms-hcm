<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Stellenbezeichnungen" icon="heroicon-o-briefcase">
            <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Übersicht" subtitle="Stellenbezeichnungen pflegen">
            <div class="flex justify-end mb-4">
                <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neu
                </x-ui-button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">Bezeichnung</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($titles as $title)
                            <tr>
                                <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $title->code }}</td>
                                <td class="px-4 py-2">{{ $title->name }}</td>
                                <td class="px-4 py-2">
                                    <x-ui-badge variant="{{ $title->is_active ? 'success' : 'secondary' }}" size="xs">
                                        {{ $title->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center">
                                    @svg('heroicon-o-briefcase', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                    <div class="text-sm text-[var(--ui-muted)]">Keine Stellenbezeichnungen gefunden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>
    </x-ui-page-container>

    <x-ui-modal wire:model="modalShow" size="md">
        <x-slot name="header">Neue Stellenbezeichnung</x-slot>
        <div class="space-y-4">
            <x-ui-input-text name="form.code" label="Code" wire:model.live="form.code" required />
            <x-ui-input-text name="form.name" label="Bezeichnung" wire:model.live="form.name" required />
            <div class="flex items-center gap-2">
                <input type="checkbox" id="jt_is_active" wire:model.live="form.is_active" class="w-4 h-4" />
                <label for="jt_is_active" class="text-sm">Aktiv</label>
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


