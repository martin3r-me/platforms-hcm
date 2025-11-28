<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Lohnart anlegen" icon="heroicon-o-plus-circle">
            <x-slot name="actions">
                <x-ui-button variant="secondary-outline" size="sm" wire:navigate href="{{ route('hcm.payroll-types.index') }}">
                    Übersicht
                </x-ui-button>
            </x-slot>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Stammdaten" subtitle="Neue Lohnart erfassen">
            <form wire:submit.prevent="save" class="space-y-6">
                @include('hcm::livewire.payroll-type.partials.form-fields', ['prefix' => 'form.', 'financeAccounts' => $financeAccounts])

                <div class="flex justify-end gap-3">
                    <x-ui-button type="button" variant="secondary-outline" wire:navigate href="{{ route('hcm.payroll-types.index') }}">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button type="submit" variant="primary">
                        Speichern
                    </x-ui-button>
                </div>
            </form>
        </x-ui-panel>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="primary" size="sm" wire:click="save" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-check', 'w-4 h-4')
                                Speichern
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" wire:navigate href="{{ route('hcm.payroll-types.index') }}" class="w-full">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Zur Übersicht
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Hinweise --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Hinweise</h3>
                    <div class="text-sm text-[var(--ui-muted)] space-y-2">
                        <p>Bitte füllen Sie alle Pflichtfelder aus. Die Lohnart kann nach dem Speichern bearbeitet werden.</p>
                        <p>Die Soll- und Haben-Konten werden aus dem Finance-Modul geladen.</p>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-3 text-sm">
                <div class="text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

