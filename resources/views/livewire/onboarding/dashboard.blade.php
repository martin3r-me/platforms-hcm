<div class="h-full" wire:poll.15s="refreshDashboard">
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar icon="heroicon-o-clipboard-document-check">
            <x-slot name="title">
                <span class="flex items-center gap-2">
                    Onboarding Dashboard
                    <span class="relative flex h-2.5 w-2.5" title="Live-Updates aktiv">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                </span>
            </x-slot>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-clipboard-document-check', 'w-6 h-6 text-purple-600')
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $this->onboardingCount }}</div>
                        <div class="text-sm text-[var(--ui-muted)]">Aktive Onboardings</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stellen-Filter --}}
        @if($this->positionGroups->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="$set('positionFilter', null)"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors {{ !$this->positionFilter ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}"
                >
                    Alle
                </button>
                @foreach($this->positionGroups as $title => $count)
                    <button
                        wire:click="$set('positionFilter', '{{ $title }}')"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors {{ $this->positionFilter === $title ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}"
                    >
                        {{ $title }}
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 rounded-full text-[10px] {{ $this->positionFilter === $title ? 'bg-white/20 text-white' : 'bg-[var(--ui-muted-10)] text-[var(--ui-muted)]' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Eingang --}}
        <x-ui-panel title="Eingang" subtitle="Neue Onboardings ohne Enrichment">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Extra-Felder</th>
                            <th class="px-4 py-3">Kontakt</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->inboxOnboardings as $onboarding)
                            @php
                                $primaryContact = $onboarding->crmContactLinks->first()?->contact;
                                $extraCounts = $this->getExtraFieldCounts($onboarding);
                                $waStatus = $this->getWhatsAppStatus($onboarding);
                            @endphp
                            <tr class="hover:bg-[var(--ui-muted-5)] transition-colors">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-start gap-2.5">
                                        <div class="mt-1.5 flex-shrink-0">
                                            <span class="relative flex h-2.5 w-2.5" title="Neu im Eingang">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-[var(--ui-secondary)]">
                                                    {{ $primaryContact?->full_name ?? 'Onboarding #' . $onboarding->id }}
                                                </span>
                                                @if($waStatus['color'] !== 'none')
                                                    <span title="{{ $waStatus['window_open'] ? 'WhatsApp Fenster offen' : ($waStatus['color'] === 'yellow' ? 'WhatsApp verfügbar' : 'WhatsApp unbekannt') }}"
                                                          class="inline-flex items-center {{ $waStatus['color'] === 'green' ? 'text-green-500' : ($waStatus['color'] === 'yellow' ? 'text-yellow-500' : 'text-gray-400') }}">
                                                        @if($waStatus['color'] === 'green')
                                                            <span class="relative flex h-3.5 w-3.5">
                                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                                @svg('heroicon-s-chat-bubble-left', 'relative w-3.5 h-3.5')
                                                            </span>
                                                        @else
                                                            @svg('heroicon-o-chat-bubble-left', 'w-3.5 h-3.5')
                                                        @endif
                                                    </span>
                                                @endif
                                            </div>
                                            @if($onboarding->source_position_title)
                                                <div class="text-xs text-[var(--ui-muted)] truncate">{{ $onboarding->source_position_title }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($extraCounts['total'] > 0)
                                        <span class="text-xs {{ $extraCounts['filled'] === $extraCounts['total'] ? 'text-green-600 font-medium' : 'text-[var(--ui-muted)]' }}">
                                            {{ $extraCounts['filled'] }}/{{ $extraCounts['total'] }}
                                        </span>
                                    @else
                                        <span class="text-xs text-[var(--ui-muted)]">&ndash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($primaryContact)
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ $primaryContact->full_name }}</span>
                                    @else
                                        <x-ui-badge variant="warning" size="xs">Kein Kontakt</x-ui-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            wire:click="markAsEnriched({{ $onboarding->id }})"
                                            class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors"
                                            title="In Bearbeitung verschieben"
                                        >
                                            @svg('heroicon-o-arrow-right', 'w-3.5 h-3.5')
                                        </button>
                                        <button
                                            wire:click="dismissOnboarding({{ $onboarding->id }})"
                                            wire:confirm="Onboarding wirklich deaktivieren?"
                                            class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                                            title="Deaktivieren"
                                        >
                                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                        </button>
                                        <x-ui-button size="sm" variant="secondary" href="{{ route('hcm.onboardings.show', $onboarding) }}" wire:navigate>
                                            @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        </x-ui-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                    <div class="flex flex-col items-center gap-2">
                                        @svg('heroicon-o-inbox', 'w-8 h-8 text-[var(--ui-muted)]/50')
                                        <span>Eingang leer</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>

        {{-- In Bearbeitung --}}
        <x-ui-panel title="In Bearbeitung" subtitle="Onboardings mit Enrichment, aber noch nicht vollständig">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Extra-Felder</th>
                            <th class="px-4 py-3">AutoPilot</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->inProgressOnboardings as $onboarding)
                            @php
                                $primaryContact = $onboarding->crmContactLinks->first()?->contact;
                                $extraCounts = $this->getExtraFieldCounts($onboarding);
                                $primaryEmail = $primaryContact?->emailAddresses?->first()?->email_address;
                                $waStatus = $this->getWhatsAppStatus($onboarding);
                            @endphp
                            <tr class="hover:bg-[var(--ui-muted-5)] transition-colors">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-start gap-2.5">
                                        <div class="mt-1.5 flex-shrink-0">
                                            <span class="relative flex h-2.5 w-2.5" title="In Bearbeitung">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-yellow-500"></span>
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-[var(--ui-secondary)] truncate">
                                                    {{ $primaryContact?->full_name ?? 'Onboarding #' . $onboarding->id }}
                                                </span>
                                                @if($waStatus['color'] !== 'none')
                                                    <span title="{{ $waStatus['window_open'] ? 'WhatsApp Fenster offen' : ($waStatus['color'] === 'yellow' ? 'WhatsApp verfügbar' : 'WhatsApp unbekannt') }}"
                                                          class="inline-flex items-center flex-shrink-0 {{ $waStatus['color'] === 'green' ? 'text-green-500' : ($waStatus['color'] === 'yellow' ? 'text-yellow-500' : 'text-gray-400') }}">
                                                        @if($waStatus['color'] === 'green')
                                                            <span class="relative flex h-3.5 w-3.5">
                                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                                @svg('heroicon-s-chat-bubble-left', 'relative w-3.5 h-3.5')
                                                            </span>
                                                        @else
                                                            @svg('heroicon-o-chat-bubble-left', 'w-3.5 h-3.5')
                                                        @endif
                                                    </span>
                                                @endif
                                            </div>
                                            @if($onboarding->source_position_title)
                                                <div class="text-xs text-[var(--ui-muted)] truncate">{{ $onboarding->source_position_title }}</div>
                                            @endif
                                            @if($primaryEmail)
                                                <div class="text-xs text-[var(--ui-muted)] truncate">{{ $primaryEmail }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($extraCounts['total'] > 0)
                                        <span class="text-xs {{ $extraCounts['filled'] === $extraCounts['total'] ? 'text-green-600 font-medium' : 'text-[var(--ui-muted)]' }}">
                                            {{ $extraCounts['filled'] }}/{{ $extraCounts['total'] }}
                                        </span>
                                    @else
                                        <span class="text-xs text-[var(--ui-muted)]">&ndash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-1">
                                        @foreach(['whatsapp', 'email'] as $type)
                                            @php
                                                $isActive = $onboarding->auto_pilot
                                                    && $onboarding->preferredCommsChannel?->type === $type;
                                                $hasChannel = $this->teamChannels->contains(fn ($c) => $c->type === $type);
                                            @endphp
                                            @if($hasChannel)
                                                <button
                                                    wire:click="toggleAutoPilot({{ $onboarding->id }}, '{{ $type }}')"
                                                    class="inline-flex items-center gap-1 rounded px-1.5 py-1 text-xs transition-colors {{ $isActive ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:bg-[var(--ui-muted-10)] hover:text-[var(--ui-secondary)]' }}"
                                                    title="{{ $type === 'whatsapp' ? 'WhatsApp AutoPilot' : 'Email AutoPilot' }}"
                                                >
                                                    @if($isActive)
                                                        <span class="relative flex h-2 w-2">
                                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                        </span>
                                                    @endif
                                                    @svg($type === 'whatsapp' ? 'heroicon-o-chat-bubble-left' : 'heroicon-o-envelope', 'w-3.5 h-3.5')
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            wire:click="dismissOnboarding({{ $onboarding->id }})"
                                            wire:confirm="Onboarding wirklich deaktivieren?"
                                            class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                                            title="Deaktivieren"
                                        >
                                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                        </button>
                                        <x-ui-button size="sm" variant="primary" href="{{ route('hcm.onboardings.show', $onboarding) }}" wire:navigate>
                                            Anzeigen
                                        </x-ui-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-[var(--ui-muted)]">Keine Onboardings in Bearbeitung</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>

        {{-- Fertig --}}
        <x-ui-panel title="Fertig" subtitle="Kontakt verknüpft, alle Felder gefüllt">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/60 text-xs uppercase tracking-wide">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Stelle</th>
                            <th class="px-4 py-3">Erstellt</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]/60">
                        @forelse($this->completedOnboardings as $onboarding)
                            @php
                                $primaryContact = $onboarding->crmContactLinks->first()?->contact;
                                $primaryEmail = $primaryContact?->emailAddresses?->first()?->email_address;
                                $waStatus = $this->getWhatsAppStatus($onboarding);
                            @endphp
                            <tr class="hover:bg-[var(--ui-muted-5)] transition-colors">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-start gap-2.5">
                                        <div class="mt-1.5 flex-shrink-0">
                                            <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-[var(--ui-secondary)] truncate">
                                                    {{ $primaryContact?->full_name ?? 'Onboarding #' . $onboarding->id }}
                                                </span>
                                                @if($waStatus['color'] !== 'none')
                                                    <span title="{{ $waStatus['window_open'] ? 'WhatsApp Fenster offen' : ($waStatus['color'] === 'yellow' ? 'WhatsApp verfügbar' : 'WhatsApp unbekannt') }}"
                                                          class="inline-flex items-center flex-shrink-0 {{ $waStatus['color'] === 'green' ? 'text-green-500' : ($waStatus['color'] === 'yellow' ? 'text-yellow-500' : 'text-gray-400') }}">
                                                        @if($waStatus['color'] === 'green')
                                                            <span class="relative flex h-3.5 w-3.5">
                                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                                @svg('heroicon-s-chat-bubble-left', 'relative w-3.5 h-3.5')
                                                            </span>
                                                        @else
                                                            @svg('heroicon-o-chat-bubble-left', 'w-3.5 h-3.5')
                                                        @endif
                                                    </span>
                                                @endif
                                            </div>
                                            @if($primaryEmail)
                                                <div class="text-xs text-[var(--ui-muted)] truncate">{{ $primaryEmail }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($onboarding->source_position_title)
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ $onboarding->source_position_title }}</span>
                                    @else
                                        <span class="text-[var(--ui-muted)]">&ndash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-sm text-[var(--ui-muted)]">
                                    {{ $onboarding->created_at?->format('d.m.Y') }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            wire:click="transferToEmployee({{ $onboarding->id }})"
                                            wire:confirm="Onboarding abschließen und als Mitarbeiter übernehmen?"
                                            class="inline-flex items-center gap-1.5 rounded px-2.5 py-1.5 text-xs font-medium bg-green-50 text-green-700 hover:bg-green-100 transition-colors"
                                            title="Als Mitarbeiter übernehmen"
                                        >
                                            @svg('heroicon-o-arrow-right-circle', 'w-3.5 h-3.5')
                                            Mitarbeiter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-[var(--ui-muted)]">
                                    <div class="flex flex-col items-center gap-2">
                                        @svg('heroicon-o-check-circle', 'w-8 h-8 text-[var(--ui-muted)]/50')
                                        <span>Keine abgeschlossenen Onboardings</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui-panel>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-4">
                <x-ui-button variant="primary" size="sm" class="w-full justify-start" href="{{ route('hcm.onboardings.index') }}" wire:navigate>
                    @svg('heroicon-o-clipboard-document-check', 'w-4 h-4') <span class="ml-2">Onboardings</span>
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" class="w-full justify-start" href="{{ route('hcm.employees.index') }}" wire:navigate>
                    @svg('heroicon-o-user-group', 'w-4 h-4') <span class="ml-2">Mitarbeiter</span>
                </x-ui-button>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-3 text-sm text-[var(--ui-muted)]">
                Keine Aktivitäten verfügbar
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
</div>
