<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Lohnarten" icon="heroicon-o-currency-euro" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <div class="space-y-2">
                        <x-ui-input-text 
                            name="search" 
                            wire:model.live.debounce.300ms="search" 
                            placeholder="Code, Name, Kategorie..." 
                            class="w-full" 
                            size="sm" 
                        />
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                wire:model.live="showInactive" 
                                id="showInactive" 
                                class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" 
                            />
                            <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" wire:click="openCreateModal" class="w-full justify-start">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span class="ml-2">Quick Add</span>
                        </x-ui-button>
                        <x-ui-button variant="primary" size="sm" wire:navigate href="{{ route('hcm.payroll-types.create') }}" class="w-full justify-start">
                            @svg('heroicon-o-pencil-square', 'w-4 h-4')
                            <span class="ml-2">Manuell anlegen</span>
                        </x-ui-button>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Export</h3>
                    <div class="space-y-2">
                    <a href="{{ route('hcm.payroll-types.export-csv') }}" 
                           class="inline-flex items-center gap-2 w-full justify-start px-3 py-2 text-sm font-medium text-[var(--ui-secondary)] bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 rounded-md hover:bg-[var(--ui-muted-5)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--ui-primary)]">
                        @svg('heroicon-o-document-arrow-down', 'w-4 h-4') CSV Download
                    </a>
                    <a href="{{ route('hcm.payroll-types.export-pdf') }}" 
                           class="inline-flex items-center gap-2 w-full justify-start px-3 py-2 text-sm font-medium text-[var(--ui-secondary)] bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 rounded-md hover:bg-[var(--ui-muted-5)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--ui-primary)]">
                        @svg('heroicon-o-document-text', 'w-4 h-4') HTML Export
                    </a>
                </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <!-- Aktive Lohnarten -->
        <x-ui-panel title="Aktive Lohnarten" :subtitle="count($activePayrollTypes) . ' Einträge'">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">LANR</th>
                            <th class="px-4 py-2">Bezeichnung</th>
                            <th class="px-4 py-2">Kategorie</th>
                            <th class="px-4 py-2">Art</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($activePayrollTypes as $type)
                            <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">
                                    <a wire:navigate href="{{ route('hcm.payroll-types.show', $type) }}" class="hover:underline">
                                        {{ $type->code }}
                                    </a>
                                </td>
                                <td class="px-4 py-2">
                                    @if($type->lanr)
                                        <span class="text-[var(--ui-muted)]">{{ $type->lanr }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div>
                                        <div class="font-medium flex items-center gap-2">
                                            <a wire:navigate href="{{ route('hcm.payroll-types.show', $type) }}" class="hover:underline">
                                                {{ $type->name }}
                                            </a>
                                        </div>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center">
                                    @svg('heroicon-o-currency-euro', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                    <div class="text-sm text-[var(--ui-muted)]">Keine aktiven Lohnarten gefunden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>

        <!-- Inaktive Lohnarten (nur wenn showInactive aktiviert) -->
        @if($showInactive && $inactivePayrollTypes->count() > 0)
            <x-ui-panel title="Inaktive Lohnarten" :subtitle="count($inactivePayrollTypes) . ' Einträge'" class="mt-6">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse text-sm opacity-75">
                        <thead>
                            <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2">Code</th>
                                <th class="px-4 py-2">LANR</th>
                                <th class="px-4 py-2">Bezeichnung</th>
                                <th class="px-4 py-2">Kategorie</th>
                                <th class="px-4 py-2">Art</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ui-border)]/60">
                            @foreach($inactivePayrollTypes as $type)
                                <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                    <td class="px-4 py-2 font-medium text-[var(--ui-secondary)]">
                                        <a wire:navigate href="{{ route('hcm.payroll-types.show', $type) }}" class="hover:underline">
                                            {{ $type->code }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($type->lanr)
                                            <span class="text-[var(--ui-muted)]">{{ $type->lanr }}</span>
                                        @else
                                            <span class="text-[var(--ui-muted)]">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <div>
                                            <div class="font-medium flex items-center gap-2">
                                                <a wire:navigate href="{{ route('hcm.payroll-types.show', $type) }}" class="hover:underline">
                                                    {{ $type->name }}
                                                </a>
                                            </div>
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui-panel>
        @endif
    </x-ui-page-container>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-3 text-sm">
                <div class="text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

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

