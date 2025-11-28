<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$payrollType->name" icon="heroicon-o-currency-euro">
            <x-slot name="subtitle">
                Code {{ $payrollType->code }} @if($payrollType->lanr) · LANR {{ $payrollType->lanr }} @endif
            </x-slot>
            <x-slot name="actions">
                <x-ui-button variant="secondary-outline" size="sm" wire:navigate href="{{ route('hcm.payroll-types.index') }}">
                    Zur Übersicht
                </x-ui-button>
            </x-slot>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-panel title="Stammdaten" subtitle="Lohnart bearbeiten">
            @if(session()->has('message'))
                <x-ui-alert variant="success" class="mb-4">
                    {{ session('message') }}
                </x-ui-alert>
            @endif

            <form wire:submit.prevent="save" class="space-y-6">
                @include('hcm::livewire.payroll-type.partials.form-fields', ['prefix' => 'form.'])

                <div class="flex justify-between items-center">
                    <div class="text-xs text-[var(--ui-muted)]">
                        Zuletzt aktualisiert {{ $payrollType->updated_at?->diffForHumans() }}
                    </div>
                    <x-ui-button type="submit" variant="primary">
                        Änderungen speichern
                    </x-ui-button>
                </div>
            </form>
        </x-ui-panel>
    </x-ui-page-container>
</x-ui-page>

