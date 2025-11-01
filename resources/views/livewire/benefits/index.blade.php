<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Benefits" icon="heroicon-o-gift" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <x-ui-panel title="Übersicht" subtitle="Alle Benefits & Zusatzleistungen">
                <div class="flex gap-2 mb-4">
                    <select wire:model.live="filterEmployer" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                        <option value="">Alle Arbeitgeber</option>
                        @foreach($this->employers as $employer)
                            <option value="{{ $employer->id }}">{{ $employer->display_name }}</option>
                        @endforeach
                    </select>
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
                    <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" class="flex-1 max-w-xs" />
                </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide bg-gray-50">
                            <th class="px-4 py-3">Mitarbeiter</th>
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Versicherung/Anbieter</th>
                            <th class="px-4 py-3">Vertragsnummer</th>
                            <th class="px-4 py-3">AN-Anteil</th>
                            <th class="px-4 py-3">AG-Anteil</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->benefits as $benefit)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('hcm.employees.show', $benefit->employee) }}" wire:navigate class="text-blue-600 hover:underline">
                                        {{ $benefit->employee->getContact()?->full_name ?? $benefit->employee->employee_number }}
                                    </a>
                                    <div class="text-xs text-[var(--ui-muted)]">{{ $benefit->employee->employee_number }}</div>
                                </td>
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
                <div class="mt-4">
                    {{ $this->benefits->links() }}
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
                        <x-ui-button variant="primary" size="sm" class="w-full" wire:click="$dispatch('open-create-benefit-modal')">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neuer Benefit
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Filter --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Gesamt</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->benefits->total() }}</span>
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Benefits-Übersicht geladen</div>
                        <div class="text-[var(--ui-muted)]">{{ now()->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

