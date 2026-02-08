<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Bewerber" icon="heroicon-o-user-plus" />
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Übersicht" subtitle="Bewerber verwalten">
            <div class="flex justify-end items-center gap-2 mb-4">
                <x-ui-button variant="secondary" size="sm"
                    wire:click="$dispatch('open-applicant-settings')">
                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neu
                </x-ui-button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-3">Name & Kontakt</th>
                            <th class="px-4 py-3">E-Mail</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Verantwortlicher</th>
                            <th class="px-4 py-3">Fortschritt</th>
                            <th class="px-4 py-3">AutoPilot</th>
                            <th class="px-4 py-3">Bewerbungsdatum</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->applicants as $applicant)
                            @php
                                $primaryContact = $applicant->crmContactLinks->first()?->contact;
                                $primaryEmail = $primaryContact?->emailAddresses->first()?->email_address;
                            @endphp
                            <tr class="hover:bg-[var(--ui-muted-5)] transition-colors">
                                <td class="px-4 py-3">
                                    @if($primaryContact)
                                        <div class="space-y-1">
                                            <div class="font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                                                {{ $primaryContact->full_name }}
                                                @if($applicant->is_active)
                                                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-[var(--ui-muted)] italic">Kein Kontakt verknüpft</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($primaryEmail)
                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-1">
                                            @svg('heroicon-o-envelope', 'w-3 h-3')
                                            {{ $primaryEmail }}
                                        </div>
                                    @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($applicant->applicantStatus)
                                        <x-ui-badge variant="primary" size="xs">
                                            {{ $applicant->applicantStatus->name }}
                                        </x-ui-badge>
                                    @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($applicant->ownedByUser)
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-full flex items-center justify-center text-xs font-medium">
                                                {{ strtoupper(substr($applicant->ownedByUser->name, 0, 1)) }}
                                            </div>
                                            <span class="text-sm">{{ $applicant->ownedByUser->fullname ?? $applicant->ownedByUser->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $applicant->progress }}%"></div>
                                        </div>
                                        <span class="text-xs text-[var(--ui-muted)]">{{ $applicant->progress }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($applicant->auto_pilot)
                                        <x-ui-badge variant="success" size="xs">An</x-ui-badge>
                                        @if($applicant->auto_pilot_completed_at)
                                            <div class="text-xs text-[var(--ui-muted)] mt-1">
                                                {{ $applicant->auto_pilot_completed_at->format('d.m.Y') }}
                                            </div>
                                        @endif
                                    @else
                                        <x-ui-badge variant="secondary" size="xs">Aus</x-ui-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($applicant->applied_at)
                                        <div class="flex items-center gap-1 text-sm">
                                            @svg('heroicon-o-calendar', 'w-4 h-4 text-[var(--ui-muted)]')
                                            <span>{{ $applicant->applied_at->format('d.m.Y') }}</span>
                                        </div>
                                    @else
                                        <span class="text-[var(--ui-muted)]">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui-badge variant="{{ $applicant->is_active ? 'success' : 'secondary' }}" size="sm">
                                        {{ $applicant->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-ui-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-ui-button
                                        size="sm"
                                        variant="primary"
                                        href="{{ route('hcm.applicants.show', ['applicant' => $applicant->id]) }}"
                                        wire:navigate
                                    >
                                        @svg('heroicon-o-pencil', 'w-3 h-3')
                                        Bearbeiten
                                    </x-ui-button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        @svg('heroicon-o-user-plus', 'w-16 h-16 text-[var(--ui-muted)] mb-4')
                                        <div class="text-lg font-medium text-[var(--ui-secondary)] mb-1">Keine Bewerber gefunden</div>
                                        <div class="text-sm text-[var(--ui-muted)]">Erstelle deinen ersten Bewerber</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>
    </x-ui-page-container>

    <!-- Create Applicant Modal -->
    <x-ui-modal wire:model="modalShow" size="md">
        <x-slot name="header">Neuer Bewerber</x-slot>
        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900">Kurzinfo</h4>
                </div>
                <p class="text-blue-700 text-sm">Erstelle einen neuen Bewerber und verknüpfe optional einen CRM-Kontakt.</p>
            </div>

            <x-ui-input-select
                name="contact_id"
                label="CRM-Kontakt (optional)"
                :options="$this->availableContacts"
                optionValue="id"
                optionLabel="display_name"
                :nullable="true"
                nullLabel="Ohne Kontakt"
                wire:model.live="contact_id"
            />

            <x-ui-input-select
                name="applicant_status_id"
                label="Bewerbungsstatus (optional)"
                :options="$this->availableStatuses"
                optionValue="id"
                optionLabel="name"
                :nullable="true"
                nullLabel="Kein Status"
                wire:model.live="applicant_status_id"
            />

            <x-ui-input-date
                name="applied_at"
                label="Bewerbungsdatum"
                wire:model.live="applied_at"
                :nullable="true"
            />

            <x-ui-input-textarea
                name="notes"
                label="Notizen"
                wire:model.live="notes"
                placeholder="Zusätzliche Notizen (optional)"
                rows="3"
            />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeCreateModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createApplicant">
                    Anlegen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    <livewire:hcm.applicant.applicant-settings-modal />

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suchen</h3>
                    <x-ui-input-text name="search" placeholder="Name suchen…" wire:model.live.debounce.300ms="search" />
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" wire:click="openCreateModal" class="w-full justify-start">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span class="ml-2">Neuer Bewerber</span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-3 text-sm">
                        <div class="text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
