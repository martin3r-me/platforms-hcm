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
                                    @if($issue->title)
                                        <div class="text-xs text-[var(--ui-muted)] mt-1">{{ $issue->title }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $issue->identifier ?? ($issue->title ?? '—') }}</td>
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
                                    <div class="flex gap-1">
                                        @if($issue->signature_data)
                                            <a href="{{ route('hcm.issues.pdf', $issue) }}" target="_blank" class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:bg-gray-50 transition">
                                                @svg('heroicon-o-document-arrow-down', 'w-4 h-4')
                                            </a>
                                        @endif
                                        <x-ui-button variant="secondary-outline" size="xs" wire:click="openEditModal({{ $issue->id }})">
                                            Bearbeiten
                                        </x-ui-button>
                                    </div>
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
                        <x-ui-button variant="primary" size="sm" class="w-full" wire:click="openCreateModal">
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
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->issues->count() }}</span>
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

    {{-- Create/Edit Modal --}}
    <x-ui-modal wire:model="showModal" size="lg" :title="$editingIssue ? 'Ausgabe bearbeiten' : 'Neue Ausgabe'">
        <div class="space-y-4">
            {{-- Basis-Felder --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-ui-input-select 
                        name="employee_id"
                        wire:model="employee_id" 
                        label="Mitarbeiter *"
                        :options="$this->employees"
                        option-value="id"
                        option-label="label"
                        placeholder="Mitarbeiter auswählen"
                    />
                </div>
                <div>
                    <x-ui-input-select 
                        name="issue_type_id"
                        wire:model.live="issue_type_id" 
                        label="Typ *"
                        :options="$this->issueTypes->map(fn($t) => ['id' => $t->id, 'label' => $t->name])->toArray()"
                        option-value="id"
                        option-label="label"
                        placeholder="Typ auswählen"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-text 
                    name="title"
                    wire:model="title" 
                    label="Titel"
                    placeholder="z.B. Laptop E15 Gen 4"
                />
                <x-ui-input-text 
                    name="identifier"
                    wire:model="identifier" 
                    label="Identifikation/Seriennummer"
                    placeholder="z.B. KB-LEVE15-49"
                />
            </div>

            <x-ui-input-textarea 
                name="description"
                wire:model="description" 
                label="Beschreibung"
                placeholder="Detaillierte Beschreibung..."
                rows="3"
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui-input-date 
                    name="issued_at"
                    wire:model="issued_at" 
                    label="Ausgegeben am"
                />
                <x-ui-input-date 
                    name="returned_at"
                    wire:model="returned_at" 
                    label="Zurückgegeben am"
                />
            </div>

            {{-- Flexible Felder basierend auf Issue-Type --}}
            @if($this->selectedIssueType && $this->selectedIssueType->field_definitions)
                <div class="border-t border-[var(--ui-border)] pt-4 mt-4">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">Zusätzliche Informationen</h3>
                    <div class="space-y-4">
                        @foreach($this->selectedIssueType->field_definitions as $field)
                            @php
                                $fieldKey = 'metadata.' . $field['key'];
                                $fieldValue = $metadata[$field['key']] ?? '';
                            @endphp
                            
                            @if($field['type'] === 'text')
                                <x-ui-input-text 
                                    :name="$fieldKey"
                                    wire:model="metadata.{{ $field['key'] }}" 
                                    :label="$field['label']"
                                    :placeholder="$field['placeholder'] ?? ''"
                                    :required="$field['required'] ?? false"
                                />
                            @elseif($field['type'] === 'textarea')
                                <x-ui-input-textarea 
                                    :name="$fieldKey"
                                    wire:model="metadata.{{ $field['key'] }}" 
                                    :label="$field['label']"
                                    :placeholder="$field['placeholder'] ?? ''"
                                    :required="$field['required'] ?? false"
                                    :rows="$field['rows'] ?? 3"
                                />
                            @elseif($field['type'] === 'select')
                                <x-ui-input-select 
                                    :name="$fieldKey"
                                    wire:model="metadata.{{ $field['key'] }}" 
                                    :label="$field['label']"
                                    :options="$field['options'] ?? []"
                                    option-value="value"
                                    option-label="label"
                                    :required="$field['required'] ?? false"
                                />
                            @elseif($field['type'] === 'checkbox')
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="checkbox" 
                                        id="field_{{ $field['key'] }}"
                                        wire:model="metadata.{{ $field['key'] }}" 
                                        class="w-4 h-4"
                                    />
                                    <label for="field_{{ $field['key'] }}" class="text-sm">
                                        {{ $field['label'] }}
                                    </label>
                                </div>
                            @elseif($field['type'] === 'date')
                                <x-ui-input-date 
                                    :name="$fieldKey"
                                    wire:model="metadata.{{ $field['key'] }}" 
                                    :label="$field['label']"
                                    :required="$field['required'] ?? false"
                                />
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <x-ui-input-textarea
                name="notes"
                wire:model="notes"
                label="Notizen"
                placeholder="Zusätzliche Notizen..."
                rows="3"
            />

            {{-- Unterschrift --}}
            @if($this->selectedIssueType && $this->selectedIssueType->requires_signature)
                <div class="border-t border-[var(--ui-border)] pt-4 mt-4">
                    <x-ui-input-signature
                        name="signature_data"
                        wire:model="signature_data"
                        label="Unterschrift des Mitarbeiters"
                        :width="500"
                        :height="200"
                    />
                    @if($editingIssue && $editingIssue->signed_at)
                        <div class="mt-2 text-sm text-[var(--ui-muted)]">
                            Unterschrieben am {{ $editingIssue->signed_at->format('d.m.Y H:i') }} Uhr
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeModal">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="save">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>

