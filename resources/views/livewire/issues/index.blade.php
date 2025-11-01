<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Ausgaben" icon="heroicon-o-archive-box" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <x-ui-panel title="Übersicht" subtitle="Alle Ausgaben & Ausstattung">
                <div class="flex gap-2 mb-4">
                    <select wire:model.live="filterEmployer" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                        <option value="">Alle Arbeitgeber</option>
                        @foreach($this->employers as $employer)
                            <option value="{{ $employer->id }}">{{ $employer->display_name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterType" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                        <option value="">Alle Typen</option>
                        @foreach($this->issueTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterStatus" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                        <option value="all">Alle</option>
                        <option value="issued">Ausgegeben</option>
                        <option value="returned">Zurückgegeben</option>
                        <option value="pending">Ausstehend</option>
                    </select>
                    <x-ui-input-text name="search" placeholder="Suchen…" wire:model.live.debounce.300ms="search" class="flex-1 max-w-xs" />
                </div>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide bg-gray-50">
                            <th class="px-4 py-3">Mitarbeiter</th>
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Identifikation</th>
                            <th class="px-4 py-3">Ausgegeben</th>
                            <th class="px-4 py-3">Zurückgegeben</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->issues as $issue)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('hcm.employees.show', $issue->employee) }}" wire:navigate class="text-blue-600 hover:underline">
                                        {{ $issue->employee->getContact()?->full_name ?? $issue->employee->employee_number }}
                                    </a>
                                    <div class="text-xs text-[var(--ui-muted)]">{{ $issue->employee->employee_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($issue->type)
                                        <x-ui-badge variant="secondary" size="xs">{{ $issue->type->name }}</x-ui-badge>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $issue->identifier ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($issue->issued_at)
                                        {{ $issue->issued_at->format('d.m.Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($issue->returned_at)
                                        {{ $issue->returned_at->format('d.m.Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($issue->returned_at)
                                        <x-ui-badge variant="success" size="xs">Zurückgegeben</x-ui-badge>
                                    @elseif($issue->issued_at)
                                        <x-ui-badge variant="warning" size="xs">Ausgegeben</x-ui-badge>
                                    @else
                                        <x-ui-badge variant="danger" size="xs">Ausstehend</x-ui-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui-button variant="secondary-outline" size="xs" wire:click="$dispatch('edit-issue', {id: {{ $issue->id }}})">
                                        Bearbeiten
                                    </x-ui-button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                    Keine Ausgaben gefunden
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
                <div class="mt-4">
                    {{ $this->issues->links() }}
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
                        <x-ui-button variant="primary" size="sm" class="w-full" wire:click="$dispatch('open-create-issue-modal')">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neue Ausgabe
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
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->issues->total() }}</span>
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Ausgaben-Übersicht geladen</div>
                        <div class="text-[var(--ui-muted)]">{{ now()->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

