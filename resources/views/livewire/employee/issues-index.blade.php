<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Ausgaben: ' . ($employee->getContact()?->full_name ?? $employee->employee_number)" icon="heroicon-o-archive-box" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-6 mb-6">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Ausgaben & Ausstattung</h2>
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Übersicht aller ausgegebenen Gegenstände für {{ $employee->getContact()?->full_name ?? $employee->employee_number }}</p>
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
        </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Navigation" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-4">
                <div>
                    <h3 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Navigation</h3>
                    <div class="space-y-1">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('hcm.employees.show', $employee)" wire:navigate class="w-full justify-start">
                            @svg('heroicon-o-arrow-left', 'w-4 h-4')
                            <span class="ml-2">Zurück zum Mitarbeiter</span>
                        </x-ui-button>
                    </div>
                </div>
                
                <div class="border-t border-[var(--ui-border)]"></div>
                
                <div>
                    <h3 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Aktionen</h3>
                    <div class="space-y-1">
                        <x-ui-button variant="primary" size="sm" wire:click="$dispatch('open-create-issue-modal')" class="w-full justify-start">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span class="ml-2">Neue Ausgabe</span>
                        </x-ui-button>
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

