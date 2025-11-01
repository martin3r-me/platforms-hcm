<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Benefits: ' . $employee->full_name" icon="heroicon-o-gift">
            <div class="flex items-center gap-2">
                <a href="{{ route('hcm.employees.show', $employee) }}" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]" wire:navigate>
                    ← {{ $employee->employee_number }}
                </a>
                <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" class="w-64" />
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Benefits & Zusatzleistungen</h2>
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Übersicht aller Benefits für {{ $employee->full_name }}</p>
                </div>
                <x-ui-button variant="primary" size="sm" wire:click="$dispatch('open-create-benefit-modal')">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neu
                </x-ui-button>
            </div>

            <div class="flex gap-2 mb-4">
                <select wire:model.live="filterType" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                    <option value="">Alle Typen</option>
                    @foreach($this->benefitTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterActive" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                    <option value="all">Alle</option>
                    <option value="active">Aktiv</option>
                    <option value="inactive">Inaktiv</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide bg-gray-50">
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Versicherung/Anbieter</th>
                            <th class="px-4 py-3">Vertragsnummer</th>
                            <th class="px-4 py-3">AN-Anteil</th>
                            <th class="px-4 py-3">AG-Anteil</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Zeitraum</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->benefits as $benefit)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <x-ui-badge variant="info" size="xs">{{ $this->benefitTypeOptions[$benefit->benefit_type] ?? $benefit->benefit_type }}</x-ui-badge>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $benefit->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $benefit->insurance_company ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $benefit->contract_number ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($benefit->monthly_contribution_employee)
                                        {{ number_format((float)$benefit->monthly_contribution_employee, 2, ',', '.') }} €
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($benefit->monthly_contribution_employer)
                                        {{ number_format((float)$benefit->monthly_contribution_employer, 2, ',', '.') }} €
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($benefit->is_active)
                                        <x-ui-badge variant="success" size="xs">Aktiv</x-ui-badge>
                                    @else
                                        <x-ui-badge variant="danger" size="xs">Inaktiv</x-ui-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-[var(--ui-muted)]">
                                    @if($benefit->start_date)
                                        {{ $benefit->start_date->format('d.m.Y') }}
                                    @endif
                                    @if($benefit->end_date)
                                        – {{ $benefit->end_date->format('d.m.Y') }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui-button variant="secondary-outline" size="xs" wire:click="$dispatch('edit-benefit', {id: {{ $benefit->id }}})">
                                        Bearbeiten
                                    </x-ui-button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                    Keine Benefits gefunden
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-[var(--ui-border)]/60">
                {{ $this->benefits->links() }}
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>

