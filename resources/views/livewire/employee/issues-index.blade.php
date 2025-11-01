<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Ausgaben: ' . $employee->full_name" icon="heroicon-o-archive-box">
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
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Ausgaben & Ausstattung</h2>
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Übersicht aller ausgegebenen Gegenstände für {{ $employee->full_name }}</p>
                </div>
                <x-ui-button variant="primary" size="sm" wire:click="$dispatch('open-create-issue-modal')">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neu
                </x-ui-button>
            </div>

            <div class="flex gap-2 mb-4">
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
            </div>
        </div>

        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide bg-gray-50">
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Identifikation</th>
                            <th class="px-4 py-3">Ausgegeben</th>
                            <th class="px-4 py-3">Zurückgegeben</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Notizen</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->issues as $issue)
                            <tr class="hover:bg-gray-50">
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
                                <td class="px-4 py-3 text-xs text-[var(--ui-muted)] max-w-xs truncate">{{ $issue->notes ?? '—' }}</td>
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
            <div class="px-4 py-3 border-t border-[var(--ui-border)]/60">
                {{ $this->issues->links() }}
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>

