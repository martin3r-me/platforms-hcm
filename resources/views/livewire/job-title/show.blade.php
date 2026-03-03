<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$jobTitle->name" icon="heroicon-o-briefcase" />
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-center gap-2 mb-6">
                @svg('heroicon-o-briefcase', 'w-6 h-6 text-blue-600')
                <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Stellenbezeichnung</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui-input-text name="jobTitle.code" label="Code" wire:model.live="jobTitle.code" required />
                <x-ui-input-text name="jobTitle.name" label="Bezeichnung" wire:model.live="jobTitle.name" required />
                <x-ui-input-select name="jobTitle.owned_by_user_id" label="Verantwortlicher" :options="$this->teamUsers" optionValue="id" optionLabel="name" :nullable="true" nullLabel="Kein Verantwortlicher" wire:model.live="jobTitle.owned_by_user_id" />
                <x-ui-input-checkbox model="jobTitle.is_active" name="jobTitle.is_active" label="Aktiv" wire:model.live="jobTitle.is_active" />
            </div>
        </div>

        <x-core-extra-fields-section :definitions="$extraFieldDefinitions" :model="$jobTitle" />
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        @if($this->isDirty)
                            <x-ui-button variant="primary" size="sm" wire:click="save" class="w-full">
                                @svg('heroicon-o-check', 'w-4 h-4') Änderungen speichern
                            </x-ui-button>
                        @endif
                        <x-ui-button variant="danger-outline" size="sm" wire:click="deleteJobTitle" wire:confirm="Stellenbezeichnung wirklich löschen?" class="w-full">
                            @svg('heroicon-o-trash', 'w-4 h-4') Stellenbezeichnung löschen
                        </x-ui-button>
                    </div>
                </div>
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
