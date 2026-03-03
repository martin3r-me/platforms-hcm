<x-ui-modal size="sm" model="transferModalShow">
    <x-slot name="header">
        Ins Onboarding überführen
    </x-slot>

    <div class="space-y-4">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-2">
                @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-amber-600')
                <h4 class="font-medium text-amber-900">Hinweis</h4>
            </div>
            <p class="text-amber-700 text-sm">Der Bewerber wird nach der Überführung deaktiviert.</p>
        </div>

        @if($transferModalShow)
            <div class="space-y-3">
                @php
                    $transferContact = $applicant->crmContactLinks->first()?->contact;
                    $transferFieldCount = $this->transferableFieldCount;
                @endphp

                <div class="flex items-center gap-3 p-3 bg-[var(--ui-muted-5)] rounded-lg">
                    @svg('heroicon-o-user', 'w-5 h-5 text-[var(--ui-muted)]')
                    <div>
                        <div class="text-sm font-medium text-[var(--ui-secondary)]">Kontakt</div>
                        <div class="text-sm text-[var(--ui-muted)]">
                            {{ $transferContact?->full_name ?? 'Kein Kontakt verknüpft' }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 bg-[var(--ui-muted-5)] rounded-lg">
                    @svg('heroicon-o-document-text', 'w-5 h-5 text-[var(--ui-muted)]')
                    <div>
                        <div class="text-sm font-medium text-[var(--ui-secondary)]">Extra-Felder</div>
                        <div class="text-sm text-[var(--ui-muted)]">
                            {{ $transferFieldCount }} {{ $transferFieldCount === 1 ? 'Feld wird' : 'Felder werden' }} übertragen
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <x-ui-button type="button" variant="secondary-outline" wire:click="$set('transferModalShow', false)">
                Abbrechen
            </x-ui-button>
            <x-ui-button type="button" variant="primary" wire:click="transferToOnboarding">
                @svg('heroicon-o-arrow-right', 'w-4 h-4')
                Überführen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
