<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Lohnarten" icon="heroicon-o-currency-euro">
            <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Übersicht" subtitle="Lohnarten verwalten">
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
                            <th class="px-4 py-2">Kategorie</th>
                            <th class="px-4 py-2">Basis</th>
                            <th class="px-4 py-2">Satz</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($payrollTypes as $type)
                            <tr>
                                <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $type->code }}</td>
                                <td class="px-4 py-2">{{ $type->name }}</td>
                                <td class="px-4 py-2">
                                    @if($type->category)
                                        <x-ui-badge variant="secondary" size="xs">{{ $type->category }}</x-ui-badge>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($type->basis)
                                        <span class="text-[var(--ui-muted)]">{{ $type->basis }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($type->default_rate)
                                        <span class="font-medium">{{ number_format($type->default_rate, 2) }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <x-ui-badge variant="{{ $type->is_active ? 'success' : 'secondary' }}" size="xs">
                                        {{ $type->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center">
                                    @svg('heroicon-o-currency-euro', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                    <div class="text-sm text-[var(--ui-muted)]">Keine Lohnarten gefunden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $payrollTypes->links() }}</div>
        </x-ui-panel>
    </x-ui-page-container>

    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">Neue Lohnart</x-slot>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.code" label="Code" wire:model.live="form.code" required />
                <x-ui-input-text name="form.name" label="Bezeichnung" wire:model.live="form.name" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.category" label="Kategorie" wire:model.live="form.category" placeholder="z.B. earning, deduction" />
                <x-ui-input-text name="form.basis" label="Basis" wire:model.live="form.basis" placeholder="z.B. hour, day, month" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_taxable" wire:model.live="form.is_taxable" class="w-4 h-4" />
                    <label for="is_taxable" class="text-sm">Steuerpflichtig</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_social_sec" wire:model.live="form.is_social_sec" class="w-4 h-4" />
                    <label for="is_social_sec" class="text-sm">Sozialversicherungspflichtig</label>
                </div>
            </div>
            <x-ui-input-text name="form.default_rate" label="Standard-Satz" wire:model.live="form.default_rate" type="number" step="0.0001" placeholder="z.B. 15.50" />
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.valid_from" label="Gültig ab" wire:model.live="form.valid_from" type="date" />
                <x-ui-input-text name="form.valid_to" label="Gültig bis" wire:model.live="form.valid_to" type="date" />
            </div>
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
