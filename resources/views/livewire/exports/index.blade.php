<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Exports" icon="heroicon-o-arrow-down-tray">
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <div class="px-4 sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session()->has('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg" 
                     x-data="{ show: true }" 
                     x-show="show" 
                     x-transition
                     x-init="setTimeout(() => { show = false; setTimeout(() => $wire.call('clearFlashMessages'), 300); }, 5000)">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-check-circle', 'w-5 h-5 text-green-600')
                            <p class="text-green-800">{{ session('success') }}</p>
                        </div>
                        <button @click="show = false; setTimeout(() => $wire.call('clearFlashMessages'), 300)" class="text-green-600 hover:text-green-800">
                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                        </button>
                    </div>
                </div>
            @endif

            @if(session()->has('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg" 
                     x-data="{ show: true }" 
                     x-show="show" 
                     x-transition
                     x-init="setTimeout(() => { show = false; setTimeout(() => $wire.call('clearFlashMessages'), 300); }, 5000)">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-red-600')
                            <p class="text-red-800">{{ session('error') }}</p>
                        </div>
                        <button @click="show = false; setTimeout(() => $wire.call('clearFlashMessages'), 300)" class="text-red-600 hover:text-red-800">
                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                        </button>
                    </div>
                </div>
            @endif

            <x-ui-panel title="Export-Historie" subtitle="Übersicht aller durchgeführten Exports">
                {{-- Filter & Suche --}}
                <div class="flex gap-2 mb-4">
                    <x-ui-input-text 
                        name="search"
                        placeholder="Suchen…" 
                        wire:model.live.debounce.300ms="search" 
                        class="flex-1 max-w-xs" 
                    />

                    <select wire:model.live="filterType" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                        <option value="">Alle Typen</option>
                        @foreach($this->exportTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="filterStatus" class="text-sm border border-[var(--ui-border)] rounded-md px-3 py-2">
                        <option value="">Alle Status</option>
                        <option value="pending">Wartend</option>
                        <option value="processing">In Bearbeitung</option>
                        <option value="completed">Abgeschlossen</option>
                        <option value="failed">Fehlgeschlagen</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead>
                            <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide bg-gray-50">
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Typ</th>
                                <th class="px-4 py-3">Format</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Datensätze</th>
                                <th class="px-4 py-3">Größe</th>
                                <th class="px-4 py-3">Erstellt am</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ui-border)]/60">
                            @forelse($this->exports as $export)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $export->name }}</div>
                                        @if($export->template)
                                            <div class="text-xs text-[var(--ui-muted)]">{{ $export->template->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-ui-badge variant="secondary" size="xs">{{ $export->type }}</x-ui-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-[var(--ui-muted)]">{{ strtoupper($export->format) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($export->status === 'completed')
                                            <x-ui-badge variant="success" size="xs">Abgeschlossen</x-ui-badge>
                                        @elseif($export->status === 'processing')
                                            <x-ui-badge variant="info" size="xs">In Bearbeitung</x-ui-badge>
                                        @elseif($export->status === 'pending')
                                            <x-ui-badge variant="warning" size="xs">Wartend</x-ui-badge>
                                        @elseif($export->status === 'failed')
                                            <x-ui-badge variant="danger" size="xs">Fehlgeschlagen</x-ui-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-[var(--ui-muted)]">{{ $export->record_count ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($export->file_size)
                                            <span class="text-[var(--ui-muted)]">{{ number_format($export->file_size / 1024, 2) }} KB</span>
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-[var(--ui-muted)]">{{ $export->created_at->format('d.m.Y H:i') }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2">
                                            @if($export->isCompleted() && $export->file_path)
                                                <a 
                                                    href="{{ route('hcm.exports.download', $export) }}"
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition-opacity"
                                                >
                                                    @svg('heroicon-o-arrow-down-tray', 'w-3 h-3')
                                                    Download
                                                </a>
                                            @endif
                                            
                                            @if($export->error_message)
                                                <x-ui-button 
                                                    variant="danger-outline" 
                                                    size="xs"
                                                    wire:click="showErrorDetails({{ $export->id }})"
                                                    title="Fehlerdetails anzeigen"
                                                >
                                                    @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                                </x-ui-button>
                                            @endif
                                            
                                            @if($export->isCompleted() || $export->isFailed())
                                                <x-ui-button 
                                                    variant="danger-outline" 
                                                    size="xs"
                                                    wire:click="deleteExport({{ $export->id }})"
                                                    wire:confirm="Möchten Sie diesen Export wirklich löschen?"
                                                >
                                                    @svg('heroicon-o-trash', 'w-3 h-3')
                                                </x-ui-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                        Keine Exports gefunden
                                    </td>
                                </tr>
                            @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>
        </div>

        {{-- Export-Modal --}}
        <x-ui-modal wire:model="modalShow" title="Neuen Export erstellen">
            <div class="space-y-4">
                {{-- Keine Flash-Messages im Modal anzeigen --}}
                
                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                        Export-Typ <span class="text-red-600">*</span>
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
                        <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
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
                            <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>
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

        {{-- Error-Details-Modal --}}
        <x-ui-modal wire:model="showErrorModal" title="Fehlerdetails" size="lg">
            <div class="space-y-4">
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <h3 class="text-sm font-semibold text-red-800 mb-2">Export fehlgeschlagen</h3>
                    <pre class="text-xs text-red-700 whitespace-pre-wrap overflow-auto max-h-96 font-mono">{{ $errorDetails }}</pre>
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" wire:click="closeErrorModal">
                    Schließen
                </x-ui-button>
            </x-slot>
        </x-ui-modal>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="primary" size="sm" wire:click="triggerExport('infoniqa')" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neuer Export
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Schnell-Exports --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Schnell-Exports</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="triggerExport('infoniqa')" class="w-full justify-start">
                            @svg('heroicon-o-document-text', 'w-4 h-4')
                            <span class="ml-2">INFONIQA</span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="triggerExport('payroll')" class="w-full justify-start">
                            @svg('heroicon-o-currency-euro', 'w-4 h-4')
                            <span class="ml-2">Lohnarten</span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="triggerExport('employees')" class="w-full justify-start">
                            @svg('heroicon-o-user-group', 'w-4 h-4')
                            <span class="ml-2">Mitarbeiter</span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Gesamt</span>
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->statistics['total'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Abgeschlossen</span>
                            <span class="font-semibold text-green-600">{{ $this->statistics['completed'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Wartend</span>
                            <span class="font-semibold text-yellow-600">{{ $this->statistics['pending'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">In Bearbeitung</span>
                            <span class="font-semibold text-blue-600">{{ $this->statistics['processing'] }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <span class="text-sm text-[var(--ui-muted)]">Fehlgeschlagen</span>
                            <span class="font-semibold text-red-600">{{ $this->statistics['failed'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6">
                <p class="text-sm text-[var(--ui-muted)]">Aktivitäten werden hier angezeigt</p>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

