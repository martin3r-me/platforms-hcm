<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Lohnarten" icon="heroicon-o-currency-euro">
            <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Übersicht" subtitle="Lohnarten verwalten">
            <div class="flex justify-between items-center mb-4">
                <div class="flex gap-2">
                    <a href="{{ route('hcm.payroll-types.export-csv') }}" 
                       class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        @svg('heroicon-o-document-arrow-down', 'w-4 h-4') CSV Download
                    </a>
                    <a href="{{ route('hcm.payroll-types.export-pdf') }}" 
                       class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        @svg('heroicon-o-document-text', 'w-4 h-4') PDF Download
                    </a>
                </div>
                <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neu
                </x-ui-button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">LANR</th>
                            <th class="px-4 py-2">Bezeichnung</th>
                            <th class="px-4 py-2">Kategorie</th>
                            <th class="px-4 py-2">Art</th>
                            <th class="px-4 py-2">Soll-Konto</th>
                            <th class="px-4 py-2">Haben-Konto</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($payrollTypes as $type)
                            <tr>
                                <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">{{ $type->code }}</td>
                                <td class="px-4 py-2">
                                    @if($type->lanr)
                                        <span class="text-[var(--ui-muted)]">{{ $type->lanr }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div>
                                        <div class="font-medium">{{ $type->name }}</div>
                                        @if($type->short_name)
                                            <div class="text-xs text-[var(--ui-muted)]">{{ $type->short_name }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    @if($type->category)
                                        <x-ui-badge variant="secondary" size="xs">{{ $type->category }}</x-ui-badge>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($type->addition_deduction)
                                        <x-ui-badge variant="{{ $type->addition_deduction === 'addition' ? 'success' : ($type->addition_deduction === 'deduction' ? 'danger' : 'secondary') }}" size="xs">
                                            {{ $type->addition_deduction === 'addition' ? 'Zuschlag' : ($type->addition_deduction === 'deduction' ? 'Abzug' : 'Neutral') }}
                                        </x-ui-badge>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($type->debitFinanceAccount)
                                        <div class="text-xs">
                                            <div class="font-medium">{{ $type->debitFinanceAccount->number }}</div>
                                            <div class="text-[var(--ui-muted)]">{{ $type->debitFinanceAccount->name }}</div>
                                        </div>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($type->creditFinanceAccount)
                                        <div class="text-xs">
                                            <div class="font-medium">{{ $type->creditFinanceAccount->number }}</div>
                                            <div class="text-[var(--ui-muted)]">{{ $type->creditFinanceAccount->name }}</div>
                                        </div>
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
                                <td colspan="8" class="px-4 py-8 text-center">
                                    @svg('heroicon-o-currency-euro', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                    <div class="text-sm text-[var(--ui-muted)]">Keine Lohnarten gefunden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>
    </x-ui-page-container>

    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">Neue Lohnart</x-slot>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.code" label="Code" wire:model.live="form.code" required />
                <x-ui-input-text name="form.lanr" label="LANR" wire:model.live="form.lanr" placeholder="Lohnarten-Nummer" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.name" label="Bezeichnung" wire:model.live="form.name" required />
                <x-ui-input-text name="form.short_name" label="Kurzname" wire:model.live="form.short_name" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.category" label="Kategorie" wire:model.live="form.category" placeholder="z.B. earning, deduction" />
                <x-ui-input-text name="form.basis" label="Basis" wire:model.live="form.basis" placeholder="z.B. hour, day, month" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="relevant_gross" wire:model.live="form.relevant_gross" class="w-4 h-4" />
                    <label for="relevant_gross" class="text-sm">Brutto-relevant</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="relevant_social_sec" wire:model.live="form.relevant_social_sec" class="w-4 h-4" />
                    <label for="relevant_social_sec" class="text-sm">Sozialversicherungspflichtig</label>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="relevant_tax" wire:model.live="form.relevant_tax" class="w-4 h-4" />
                    <label for="relevant_tax" class="text-sm">Steuerpflichtig</label>
                </div>
                <div>
                    <x-ui-input-select name="form.addition_deduction" label="Art" wire:model.live="form.addition_deduction" :options="[
                        'addition' => 'Zuschlag',
                        'deduction' => 'Abzug', 
                        'neutral' => 'Neutral'
                    ]" />
                </div>
            </div>
            <x-ui-input-text name="form.default_rate" label="Standard-Satz" wire:model.live="form.default_rate" type="number" step="0.0001" placeholder="z.B. 15.50" />
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.valid_from" label="Gültig ab" wire:model.live="form.valid_from" type="date" />
                <x-ui-input-text name="form.valid_to" label="Gültig bis" wire:model.live="form.valid_to" type="date" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text name="form.display_group" label="Anzeigegruppe" wire:model.live="form.display_group" placeholder="z.B. Grundlohn, Zulagen" />
                <x-ui-input-text name="form.sort_order" label="Sortierung" wire:model.live="form.sort_order" type="number" placeholder="0" />
            </div>
            <x-ui-input-textarea name="form.description" label="Beschreibung" wire:model.live="form.description" rows="3" placeholder="Zusätzliche Beschreibung der Lohnart" />
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

