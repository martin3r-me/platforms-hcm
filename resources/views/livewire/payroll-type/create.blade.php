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
                @include('hcm::livewire.payroll-type.partials.form-fields', ['prefix' => 'form.'])

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
</x-ui-page>

