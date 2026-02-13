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
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-800">
                    {{ session('message') }}
                </div>
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
                                Änderungen speichern
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

                {{-- Informationen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Informationen</h3>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-[var(--ui-muted)]">Code</dt>
                            <dd class="font-medium text-[var(--ui-secondary)]">{{ $payrollType->code }}</dd>
                        </div>
                        @if($payrollType->lanr)
                        <div>
                            <dt class="text-[var(--ui-muted)]">LANR</dt>
                            <dd class="font-medium text-[var(--ui-secondary)]">{{ $payrollType->lanr }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-[var(--ui-muted)]">Status</dt>
                            <dd>
                                <x-ui-badge variant="{{ $payrollType->is_active ? 'success' : 'secondary' }}" size="xs">
                                    {{ $payrollType->is_active ? 'Aktiv' : 'Inaktiv' }}
                                </x-ui-badge>
                            </dd>
                        </div>
                    </dl>
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

