<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Exports" icon="heroicon-o-arrow-down-tray">
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- Statistiken --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <x-ui-dashboard-tile 
                title="Gesamt" 
                :count="$this->statistics['total']" 
                icon="document-text" 
                variant="primary" 
                size="sm" 
            />
            <x-ui-dashboard-tile 
                title="Abgeschlossen" 
                :count="$this->statistics['completed']" 
                icon="check-circle" 
                variant="success" 
                size="sm" 
            />
            <x-ui-dashboard-tile 
                title="Wartend" 
                :count="$this->statistics['pending']" 
                icon="clock" 
                variant="warning" 
                size="sm" 
            />
            <x-ui-dashboard-tile 
                title="In Bearbeitung" 
                :count="$this->statistics['processing']" 
                icon="arrow-path" 
                variant="info" 
                size="sm" 
            />
            <x-ui-dashboard-tile 
                title="Fehlgeschlagen" 
                :count="$this->statistics['failed']" 
                icon="x-circle" 
                variant="danger" 
                size="sm" 
            />
        </div>

        {{-- Filter & Suche --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <x-ui-input-text 
                    name="search"
                    label="Suche"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Export-Name oder Typ suchen..."
                />

                <x-ui-input-select
                    name="filterType"
                    label="Typ"
                    wire:model.live="filterType"
                    :options="['' => 'Alle'] + $this->exportTypes"
                />

                <x-ui-input-select
                    name="filterStatus"
                    label="Status"
                    wire:model.live="filterStatus"
                    :options="[
                        '' => 'Alle',
                        'pending' => 'Wartend',
                        'processing' => 'In Bearbeitung',
                        'completed' => 'Abgeschlossen',
                        'failed' => 'Fehlgeschlagen',
                    ]"
                />

                <div class="flex items-end">
                    <x-ui-button variant="primary" wire:click="triggerExport('infoniqa')" class="w-full">
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                        Neuer Export
                    </x-ui-button>
                </div>
            </div>
        </div>

        {{-- Export-Tabelle --}}
        <x-ui-panel title="Export-Historie" subtitle="Übersicht aller durchgeführten Exports">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--ui-border)]/60">
                    <thead class="bg-[var(--ui-muted-5)]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Name
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Typ
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Format
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Datensätze
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Größe
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Erstellt am
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-[var(--ui-secondary)] uppercase tracking-wider">
                                Aktionen
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->exports as $export)
                            <tr class="hover:bg-[var(--ui-muted-5)] transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-medium text-[var(--ui-secondary)]">{{ $export->name }}</div>
                                    @if($export->template)
                                        <div class="text-xs text-[var(--ui-muted)]">{{ $export->template->name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-ui-badge variant="secondary" size="sm">{{ $export->type }}</x-ui-badge>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-[var(--ui-muted)]">
                                    {{ strtoupper($export->format) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($export->status === 'completed')
                                        <x-ui-badge variant="success" size="sm">Abgeschlossen</x-ui-badge>
                                    @elseif($export->status === 'processing')
                                        <x-ui-badge variant="info" size="sm">In Bearbeitung</x-ui-badge>
                                    @elseif($export->status === 'pending')
                                        <x-ui-badge variant="warning" size="sm">Wartend</x-ui-badge>
                                    @elseif($export->status === 'failed')
                                        <x-ui-badge variant="danger" size="sm">Fehlgeschlagen</x-ui-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-[var(--ui-muted)]">
                                    {{ $export->record_count ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-[var(--ui-muted)]">
                                    @if($export->file_size)
                                        {{ number_format($export->file_size / 1024, 2) }} KB
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-[var(--ui-muted)]">
                                    {{ $export->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($export->isCompleted() && $export->file_path)
                                            <a 
                                                href="{{ route('hcm.exports.download', $export) }}"
                                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition-opacity"
                                            >
                                                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                                                Download
                                            </a>
                                        @endif
                                        
                                        @if($export->error_message)
                                            <x-ui-button 
                                                variant="danger" 
                                                size="sm"
                                                title="{{ $export->error_message }}"
                                            >
                                                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                        
                                        @if($export->isCompleted() || $export->isFailed())
                                            <x-ui-button 
                                                variant="danger-outline" 
                                                size="sm"
                                                wire:click="deleteExport({{ $export->id }})"
                                                wire:confirm="Möchten Sie diesen Export wirklich löschen?"
                                            >
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </x-ui-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-[var(--ui-muted)]">
                                    @svg('heroicon-o-document-text', 'w-12 h-12 mx-auto mb-4 text-[var(--ui-muted)]')
                                    <div>Noch keine Exports vorhanden</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>

        {{-- Export-Modal --}}
        <x-ui-modal wire:model="showExportModal" title="Neuen Export erstellen">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                        Export-Typ
                    </label>
                    <select 
                        wire:model.live="selectedExportType" 
                        class="block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm"
                    >
                        <option value="">Bitte wählen...</option>
                        @foreach($this->exportTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('selectedExportType')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                @if($selectedExportType === 'infoniqa')
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                            Arbeitgeber <span class="text-red-600">*</span>
                        </label>
                        <select 
                            wire:model="selectedEmployerId" 
                            class="block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm"
                        >
                            <option value="">Bitte Arbeitgeber wählen...</option>
                            @foreach($this->employers as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('selectedEmployerId')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>
                @endif

                @if($selectedExportType)
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="text-sm text-blue-700">
                            <strong>Hinweis:</strong> Der Export wird im CSV-Format erstellt und kann anschließend heruntergeladen werden.
                        </div>
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-ui-button variant="secondary" wire:click="cancelExport">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button 
                        variant="primary" 
                        wire:click="executeExport"
                        :disabled="!$selectedExportType || ($selectedExportType === 'infoniqa' && !$selectedEmployerId)"
                    >
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                        Export starten
                    </x-ui-button>
                </div>
            </x-slot>
        </x-ui-modal>
    </x-ui-page-container>

    <x-slot name="left">
        <x-ui-sidebar>
            <x-ui-sidebar-list label="Aktionen">
                <x-ui-sidebar-item wire:click="triggerExport('infoniqa')">
                    @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[var(--ui-secondary)]')
                    <span class="ml-2 text-sm">Neuer Export</span>
                </x-ui-sidebar-item>
            </x-ui-sidebar-list>

            <x-ui-sidebar-list label="Schnell-Exports">
                <x-ui-sidebar-item wire:click="triggerExport('infoniqa')">
                    @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-secondary)]')
                    <span class="ml-2 text-sm">INFONIQA</span>
                </x-ui-sidebar-item>
                <x-ui-sidebar-item wire:click="triggerExport('payroll')">
                    @svg('heroicon-o-currency-euro', 'w-4 h-4 text-[var(--ui-secondary)]')
                    <span class="ml-2 text-sm">Lohnarten</span>
                </x-ui-sidebar-item>
                <x-ui-sidebar-item wire:click="triggerExport('employees')">
                    @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-secondary)]')
                    <span class="ml-2 text-sm">Mitarbeiter</span>
                </x-ui-sidebar-item>
            </x-ui-sidebar-list>
        </x-ui-sidebar>
    </x-slot>

    <x-slot name="right">
        <x-ui-sidebar>
            <x-ui-sidebar-list label="Statistiken">
                <div class="px-4 py-2 text-sm">
                    <div class="text-[var(--ui-muted)]">Gesamt Exporte</div>
                    <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->statistics['total'] }}</div>
                </div>
                <div class="px-4 py-2 text-sm">
                    <div class="text-[var(--ui-muted)]">Erfolgreich</div>
                    <div class="text-lg font-bold text-green-600">{{ $this->statistics['completed'] }}</div>
                </div>
                <div class="px-4 py-2 text-sm">
                    <div class="text-[var(--ui-muted)]">Fehlgeschlagen</div>
                    <div class="text-lg font-bold text-red-600">{{ $this->statistics['failed'] }}</div>
                </div>
            </x-ui-sidebar-list>
        </x-ui-sidebar>
    </x-slot>
</x-ui-page>

