<div class="onboarding-portal min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <style>
        .onboarding-portal input, .onboarding-portal select, .onboarding-portal textarea { color: #111827 !important; }
        .onboarding-portal label { color: #4b5563 !important; }
    </style>
    <div class="max-w-3xl mx-auto">

        {{-- Ungültiger Token --}}
        @if($state === 'invalid')
            <div class="bg-white rounded-lg border border-red-200 p-12 text-center">
                @svg('heroicon-o-exclamation-triangle', 'w-16 h-16 text-red-400 mx-auto mb-4')
                <h2 class="text-xl font-bold text-gray-900 mb-2">Ungültiger Link</h2>
                <p class="text-gray-500">Dieser Link ist ungültig oder nicht verfügbar.</p>
            </div>
        @elseif($state === 'expired')
            <div class="bg-white rounded-lg border border-yellow-200 p-12 text-center">
                @svg('heroicon-o-clock', 'w-16 h-16 text-yellow-400 mx-auto mb-4')
                <h2 class="text-xl font-bold text-gray-900 mb-2">Link abgelaufen</h2>
                <p class="text-gray-500">Dieser Link ist abgelaufen. Bitte kontaktieren Sie Ihren Arbeitgeber.</p>
            </div>
        @elseif($state === 'loading')
            <div class="bg-white rounded-lg border p-12 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-500">Wird geladen...</p>
            </div>

        {{-- OVERVIEW --}}
        @elseif($state === 'overview')
            @php
                $contracts = $this->contracts;
                $allCompleted = $contracts->count() > 0 && $contracts->every(fn ($c) => $c->status === 'completed');
                $openContracts = $contracts->filter(fn ($c) => in_array($c->status, ['sent', 'in_progress']));
            @endphp

            {{-- Alle unterschrieben --}}
            @if($allCompleted)
                <div class="bg-white rounded-lg border border-green-200 p-8 mb-6 text-center">
                    @svg('heroicon-o-check-circle', 'w-16 h-16 text-green-500 mx-auto mb-4')
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Alle Verträge unterschrieben</h2>
                    <p class="text-gray-500">Vielen Dank! Sie haben alle Verträge erfolgreich unterschrieben.</p>
                </div>

                <div class="space-y-4">
                    @foreach($contracts as $contract)
                        <div class="bg-white rounded-lg border border-gray-200 p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="flex-shrink-0">
                                        @svg('heroicon-o-check-circle', 'w-8 h-8 text-green-500')
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            {{ $contract->contractTemplate?->name ?? 'Vertrag' }}
                                        </h3>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 mt-1">
                                            Unterschrieben
                                        </span>
                                    </div>
                                </div>
                                <button type="button" wire:click="startSigning({{ $contract->id }})"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                                    @svg('heroicon-o-eye', 'w-4 h-4') Ansehen
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-lg border border-gray-200 p-8 mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">
                        @if($candidateName)
                            Willkommen, {{ $candidateName }}!
                        @else
                            Willkommen!
                        @endif
                    </h1>
                    <p class="text-gray-500">
                        Bitte unterschreiben Sie die folgenden Verträge. Klicken Sie auf "Unterschreiben", um den jeweiligen Vertrag durchzugehen.
                    </p>
                </div>

                <div class="space-y-4">
                    @foreach($contracts as $contract)
                        <div class="bg-white rounded-lg border border-gray-200 p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="flex-shrink-0">
                                        @if($contract->status === 'completed')
                                            @svg('heroicon-o-check-circle', 'w-8 h-8 text-green-500')
                                        @else
                                            @svg('heroicon-o-document-text', 'w-8 h-8 text-gray-400')
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            {{ $contract->contractTemplate?->name ?? 'Vertrag' }}
                                        </h3>
                                        <div class="mt-1">
                                            @php
                                                $statusConfig = match($contract->status) {
                                                    'pending' => ['label' => 'Ausstehend', 'classes' => 'bg-gray-100 text-gray-600'],
                                                    'sent' => ['label' => 'Offen', 'classes' => 'bg-blue-100 text-blue-700'],
                                                    'in_progress' => ['label' => 'In Bearbeitung', 'classes' => 'bg-yellow-100 text-yellow-700'],
                                                    'completed' => ['label' => 'Unterschrieben', 'classes' => 'bg-green-100 text-green-700'],
                                                    default => ['label' => $contract->status, 'classes' => 'bg-gray-100 text-gray-600'],
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusConfig['classes'] }}">
                                                {{ $statusConfig['label'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    @if(in_array($contract->status, ['sent', 'in_progress']))
                                        <button type="button" wire:click="startSigning({{ $contract->id }})"
                                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                            @svg('heroicon-o-pencil', 'w-4 h-4') Unterschreiben
                                        </button>
                                    @elseif($contract->status === 'completed')
                                        <button type="button" wire:click="startSigning({{ $contract->id }})"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                                            @svg('heroicon-o-eye', 'w-4 h-4') Ansehen
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Field values mini-dashboard --}}
                            @if(! empty($contract->fieldValues))
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($contract->fieldValues as $field)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-gray-50 border border-gray-200 text-xs">
                                                <span class="text-gray-500">{{ $field['label'] }}:</span>
                                                <span class="font-medium text-gray-700">{{ $field['value'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

        {{-- SIGNING --}}
        @elseif($state === 'signing')
            {{-- Zurück-Button --}}
            <div class="mb-6">
                <button type="button" wire:click="backToOverview"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition">
                    @svg('heroicon-o-arrow-left', 'w-4 h-4') Zurück zur Übersicht
                </button>
            </div>

            {{-- Fortschrittsanzeige (nur beim Unterschreiben von AV, nicht bei Ansicht) --}}
            @if(! $isViewOnly && $contractTemplateCode === 'AV')
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-2">
                        @foreach([1 => '§15 Angaben', 2 => '§16 Angaben', 3 => 'Vertrag & Unterschrift'] as $num => $label)
                            <div class="flex items-center {{ $num < 3 ? 'flex-1' : '' }}">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                                    {{ $step >= $num ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' }}">
                                    @if($step > $num)
                                        @svg('heroicon-o-check', 'w-5 h-5')
                                    @else
                                        {{ $num }}
                                    @endif
                                </div>
                                <span class="ml-2 text-sm font-medium {{ $step >= $num ? 'text-gray-900' : 'text-gray-400' }} hidden sm:inline">
                                    {{ $label }}
                                </span>
                                @if($num < 3)
                                    <div class="flex-1 mx-4 h-0.5 {{ $step > $num ? 'bg-blue-600' : 'bg-gray-200' }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Step 1: §15 --}}
            @if($step === 1)
                <div class="bg-white rounded-lg border border-gray-200 p-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Angaben nach §15 — Kurzfristige Beschäftigungen</h2>
                    <p class="text-gray-500 text-sm mb-6">
                        Waren Sie in den letzten 12 Monaten kurzfristig beschäftigt?
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-center gap-6">
                            <button type="button" wire:click="$set('par15HasPrevious', true)"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition
                                {{ $par15HasPrevious ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                Ja
                            </button>
                            <button type="button" wire:click="$set('par15HasPrevious', false)"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition
                                {{ !$par15HasPrevious ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                Nein
                            </button>
                        </div>

                        @if($par15HasPrevious)
                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-700">Bisherige kurzfristige Beschäftigungen</h3>
                                    <button type="button" wire:click="addPar15Entry"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50 rounded-md transition">
                                        @svg('heroicon-o-plus', 'w-4 h-4') Eintrag hinzufügen
                                    </button>
                                </div>

                                @foreach($par15Entries as $index => $entry)
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                        <div class="flex items-start justify-between mb-3">
                                            <span class="text-xs font-medium text-gray-500">Beschäftigung {{ $index + 1 }}</span>
                                            <button type="button" wire:click="removePar15Entry({{ $index }})"
                                                class="text-red-400 hover:text-red-600 transition">
                                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Beginn</label>
                                                <input type="date" wire:model.live="par15Entries.{{ $index }}.beginn"
                                                    class="w-full rounded-md border-gray-300 text-gray-900 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("par15Entries.{$index}.beginn") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Ende</label>
                                                <input type="date" wire:model.live="par15Entries.{{ $index }}.ende"
                                                    class="w-full rounded-md border-gray-300 text-gray-900 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("par15Entries.{$index}.ende") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Arbeitgeber</label>
                                                <input type="text" wire:model.live="par15Entries.{{ $index }}.arbeitgeber"
                                                    placeholder="Firma, Ort"
                                                    class="w-full rounded-md border-gray-300 text-gray-900 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("par15Entries.{$index}.arbeitgeber") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Anzahl Arbeitstage</label>
                                                <input type="number" wire:model.live="par15Entries.{{ $index }}.tage" min="1"
                                                    placeholder="z.B. 30"
                                                    class="w-full rounded-md border-gray-300 text-gray-900 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("par15Entries.{$index}.tage") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if(count($par15Entries) === 0)
                                    <p class="text-sm text-gray-400 text-center py-4">
                                        Noch keine Einträge. Klicken Sie auf "Eintrag hinzufügen".
                                    </p>
                                @endif
                                @error('par15Entries') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end mt-8">
                        <button type="button" wire:click="nextStep"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                            Weiter @svg('heroicon-o-arrow-right', 'w-4 h-4')
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 2: §16 --}}
            @if($step === 2)
                <div class="bg-white rounded-lg border border-gray-200 p-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Angaben nach §16 — Beschäftigungslose Zeiten</h2>
                    <p class="text-gray-500 text-sm mb-6">
                        Waren Sie in den letzten 12 Monaten bei der Arbeitsagentur als arbeitssuchend gemeldet?
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-center gap-6">
                            <button type="button" wire:click="$set('par16WasJobseeking', true)"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition
                                {{ $par16WasJobseeking ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                Ja
                            </button>
                            <button type="button" wire:click="$set('par16WasJobseeking', false)"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition
                                {{ !$par16WasJobseeking ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                Nein
                            </button>
                        </div>

                        @if($par16WasJobseeking)
                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-700">Beschäftigungslose Zeiten</h3>
                                    <button type="button" wire:click="addPar16Entry"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50 rounded-md transition">
                                        @svg('heroicon-o-plus', 'w-4 h-4') Eintrag hinzufügen
                                    </button>
                                </div>

                                @foreach($par16Entries as $index => $entry)
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                        <div class="flex items-start justify-between mb-3">
                                            <span class="text-xs font-medium text-gray-500">Zeitraum {{ $index + 1 }}</span>
                                            <button type="button" wire:click="removePar16Entry({{ $index }})"
                                                class="text-red-400 hover:text-red-600 transition">
                                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Beginn</label>
                                                <input type="date" wire:model.live="par16Entries.{{ $index }}.beginn"
                                                    class="w-full rounded-md border-gray-300 text-gray-900 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("par16Entries.{$index}.beginn") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Ende</label>
                                                <input type="date" wire:model.live="par16Entries.{{ $index }}.ende"
                                                    class="w-full rounded-md border-gray-300 text-gray-900 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("par16Entries.{$index}.ende") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Arbeitsagentur</label>
                                                <input type="text" wire:model.live="par16Entries.{{ $index }}.arbeitsagentur"
                                                    placeholder="Ort/Name der Agentur"
                                                    class="w-full rounded-md border-gray-300 text-gray-900 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("par16Entries.{$index}.arbeitsagentur") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if(count($par16Entries) === 0)
                                    <p class="text-sm text-gray-400 text-center py-4">
                                        Noch keine Einträge. Klicken Sie auf "Eintrag hinzufügen".
                                    </p>
                                @endif
                                @error('par16Entries') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-between mt-8">
                        <button type="button" wire:click="previousStep"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                            @svg('heroicon-o-arrow-left', 'w-4 h-4') Zurück
                        </button>
                        <button type="button" wire:click="nextStep"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                            Weiter @svg('heroicon-o-arrow-right', 'w-4 h-4')
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 3: Vertrag & Unterschrift --}}
            @if($step === 3)
                <div class="space-y-6">
                    <div class="bg-white rounded-lg border border-gray-200 p-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">{{ $contractTemplateName }}</h2>
                        <style>
                            .contract-content, .contract-content * { color: #111827 !important; }
                        </style>
                        <div class="contract-content prose prose-sm max-w-none border border-gray-100 rounded-lg p-6 bg-gray-50 max-h-[60vh] overflow-y-auto whitespace-pre-line">
                            {!! $contractContent !!}
                        </div>
                    </div>

                    {{-- View-only: Show §15/§16 data if present --}}
                    @if($isViewOnly)
                        @if($par15HasPrevious && count($par15Entries) > 0)
                            <div class="bg-white rounded-lg border border-gray-200 p-8">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Angaben nach &sect;15 &mdash; Kurzfristige Beschäftigungen</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Beginn</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Ende</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Arbeitgeber</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Tage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($par15Entries as $entry)
                                                <tr>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['beginn'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['ende'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['arbeitgeber'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['tage'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($par16WasJobseeking && count($par16Entries) > 0)
                            <div class="bg-white rounded-lg border border-gray-200 p-8">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Angaben nach &sect;16 &mdash; Beschäftigungslose Zeiten</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Beginn</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Ende</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Arbeitsagentur</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($par16Entries as $entry)
                                                <tr>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['beginn'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['ende'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['arbeitsagentur'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Show saved signature --}}
                        @if($signatureData)
                            <div class="bg-white rounded-lg border border-gray-200 p-8">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Unterschrift</h3>
                                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <img src="{{ $signatureData }}" alt="Unterschrift" class="max-h-32 mx-auto">
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-start">
                            <button type="button" wire:click="backToOverview"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4') Zurück zur Übersicht
                            </button>
                        </div>
                    @else
                        {{-- §15/§16 Zusammenfassung vor Unterschrift --}}
                        @if($par15HasPrevious && count($par15Entries) > 0)
                            <div class="bg-white rounded-lg border border-gray-200 p-8">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Ihre Angaben nach &sect;15 &mdash; Kurzfristige Beschäftigungen</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Beginn</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Ende</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Arbeitgeber</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Tage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($par15Entries as $entry)
                                                <tr>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['beginn'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['ende'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['arbeitgeber'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['tage'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($par16WasJobseeking && count($par16Entries) > 0)
                            <div class="bg-white rounded-lg border border-gray-200 p-8">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">Ihre Angaben nach &sect;16 &mdash; Beschäftigungslose Zeiten</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Beginn</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Ende</th>
                                                <th class="border border-gray-200 px-3 py-2 text-left font-medium text-gray-600">Arbeitsagentur</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($par16Entries as $entry)
                                                <tr>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['beginn'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['ende'] ?? '' }}</td>
                                                    <td class="border border-gray-200 px-3 py-2 text-gray-900">{{ $entry['arbeitsagentur'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="bg-white rounded-lg border border-gray-200 p-8">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Unterschrift</h3>
                            <p class="text-sm text-gray-500 mb-4">
                                Mit Ihrer Unterschrift bestätigen Sie, dass Sie den Vertrag gelesen haben und die oben gemachten Angaben korrekt sind.
                            </p>

                            <x-ui-input-signature
                                name="signatureData"
                                label="Ihre Unterschrift"
                                wire:model="signatureData"
                                :required="true"
                                :height="200"
                            />

                            <div class="flex justify-between mt-8">
                                <button type="button" wire:click="{{ $contractTemplateCode === 'AV' ? 'previousStep' : 'backToOverview' }}"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                                    @svg('heroicon-o-arrow-left', 'w-4 h-4') Zurück
                                </button>
                                <button type="button" wire:click="sign"
                                    class="inline-flex items-center gap-2 px-8 py-3 bg-green-600 text-white text-sm font-bold rounded-lg hover:bg-green-700 transition">
                                    @svg('heroicon-o-check', 'w-5 h-5') Vertrag unterschreiben
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>
</div>
