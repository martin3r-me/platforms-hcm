<x-ui-modal size="lg" model="modalShow">
    <x-slot name="header">
        Onboarding-Einstellungen
    </x-slot>

    <div class="p-4 space-y-6">
        <h3 class="text-lg font-medium text-[var(--ui-secondary)]">WhatsApp Template-Versand</h3>
        <p class="text-xs text-[var(--ui-muted)]">Konfigurieren Sie den WhatsApp-Account und das Template, das beim Versand des Onboarding-Portals verwendet wird.</p>

        <div class="space-y-4">
            {{-- WA Account --}}
            @if(!empty($this->availableWhatsAppAccounts))
                <x-ui-input-select
                    name="settings.onboarding_wa_account_id"
                    label="WhatsApp Account"
                    :options="$this->availableWhatsAppAccounts"
                    optionValue="id"
                    optionLabel="label"
                    :nullable="true"
                    nullLabel="– Account wählen –"
                    wire:model.live="settings.onboarding_wa_account_id"
                />
            @else
                <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-sm text-[var(--ui-muted)]">
                    Keine WhatsApp Accounts verfügbar. Accounts werden über die WhatsApp-Integration konfiguriert.
                </div>
            @endif

            {{-- WA Template --}}
            @if(!empty($this->availableWhatsAppTemplates))
                <x-ui-input-select
                    name="settings.onboarding_wa_template_id"
                    label="WhatsApp Template"
                    :options="$this->availableWhatsAppTemplates"
                    optionValue="id"
                    optionLabel="label"
                    :nullable="true"
                    nullLabel="– Template wählen –"
                    wire:model.live="settings.onboarding_wa_template_id"
                />
            @elseif(!empty($settings['onboarding_wa_account_id']))
                <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-sm text-[var(--ui-muted)]">
                    Keine genehmigten Templates für diesen Account gefunden.
                </div>
            @endif

            {{-- Variable Mapping --}}
            @if(!empty($templateBodyParamDefs))
                <div class="pt-4 mt-4 border-t border-[var(--ui-border)]/40">
                    <h4 class="text-sm font-medium text-[var(--ui-secondary)] mb-3">Template-Variablen zuordnen</h4>
                    <p class="text-xs text-[var(--ui-muted)] mb-4">Ordnen Sie jeder Template-Variable eine Datenquelle zu.</p>

                    <div class="space-y-3">
                        @foreach($templateBodyParamDefs as $param)
                            <div class="flex items-center gap-4 p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">
                                        @{{ {{ $param['name'] }} }}
                                    </span>
                                    @if($param['example'])
                                        <span class="text-xs text-[var(--ui-muted)] ml-2">(z.B. {{ $param['example'] }})</span>
                                    @endif
                                </div>
                                <div class="w-64">
                                    <select wire:model="settings.onboarding_wa_template_variables.{{ $param['name'] }}"
                                            class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-md bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]">
                                        <option value="">– Quelle wählen –</option>
                                        @foreach($this->variableSources as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>
