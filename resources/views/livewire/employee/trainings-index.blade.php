<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Schulungen: ' . ($employee->getContact()?->full_name ?? $employee->employee_number)" icon="heroicon-o-academic-cap" />
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            <x-ui-panel title="Übersicht" subtitle="Alle Schulungen & Zertifikate">
                <p class="text-sm text-[var(--ui-muted)] mt-1 mb-4">Übersicht aller Schulungen für {{ $employee->getContact()?->full_name ?? $employee->employee_number }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead>
                            <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                                <th class="px-4 py-3">Schulungsart</th>
                                <th class="px-4 py-3">Titel</th>
                                <th class="px-4 py-3">Anbieter</th>
                                <th class="px-4 py-3">Abgeschlossen</th>
                                <th class="px-4 py-3">Gültig bis</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ui-border)]/60">
                            @forelse($this->trainings as $training)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div>
                                            <div class="font-medium">{{ $training->trainingType->name ?? '—' }}</div>
                                            @if($training->trainingType?->category)
                                                <div class="text-xs text-[var(--ui-muted)]">{{ $training->trainingType->category }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ $training->title ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $training->provider ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($training->completed_date)
                                            {{ $training->completed_date->format('d.m.Y') }}
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($training->valid_until)
                                            @if($training->isExpired())
                                                <span class="text-red-600 font-medium">{{ $training->valid_until->format('d.m.Y') }}</span>
                                            @elseif($training->valid_until->diffInDays(now()) <= 30)
                                                <span class="text-orange-600 font-medium">{{ $training->valid_until->format('d.m.Y') }}</span>
                                            @else
                                                {{ $training->valid_until->format('d.m.Y') }}
                                            @endif
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($training->isExpired())
                                            <x-ui-badge variant="danger" size="xs">Abgelaufen</x-ui-badge>
                                        @elseif($training->status === 'completed')
                                            <x-ui-badge variant="success" size="xs">Abgeschlossen</x-ui-badge>
                                        @elseif($training->status === 'pending')
                                            <x-ui-badge variant="warning" size="xs">Ausstehend</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="secondary" size="xs">{{ $training->status ?? '—' }}</x-ui-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-ui-button variant="secondary-outline" size="xs" wire:click="$dispatch('edit-training', {id: {{ $training->id }}})">
                                            Bearbeiten
                                        </x-ui-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                        @svg('heroicon-o-academic-cap', 'w-10 h-10 text-[var(--ui-muted)] mx-auto mb-2')
                                        <div class="text-sm">Keine Schulungen gefunden</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui-panel>
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
                        <x-ui-button variant="primary" size="sm" wire:click="$dispatch('open-create-training-modal')" class="w-full justify-start">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span class="ml-2">Neue Schulung</span>
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
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Schulungen-Übersicht geladen</div>
                        <div class="text-[var(--ui-muted)]">{{ now()->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

