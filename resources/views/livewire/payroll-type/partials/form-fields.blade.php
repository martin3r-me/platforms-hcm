<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <x-ui-input-text :name="$prefix.'code'" label="Code" wire:model.defer="{{ $prefix }}code" required />
    <x-ui-input-text :name="$prefix.'lanr'" label="LANR" wire:model.defer="{{ $prefix }}lanr" placeholder="Lohnarten-Nummer" />
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <x-ui-input-text :name="$prefix.'name'" label="Bezeichnung" wire:model.defer="{{ $prefix }}name" required />
    <x-ui-input-text :name="$prefix.'short_name'" label="Kurzname" wire:model.defer="{{ $prefix }}short_name" />
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <x-ui-input-text :name="$prefix.'category'" label="Kategorie" wire:model.defer="{{ $prefix }}category" placeholder="earning, deduction …" />
    <x-ui-input-text :name="$prefix.'basis'" label="Basis" wire:model.defer="{{ $prefix }}basis" placeholder="hour, day, month …" />
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="flex items-center gap-2">
        <input type="checkbox" id="{{ $prefix }}relevant_gross" wire:model.defer="{{ $prefix }}relevant_gross" class="w-4 h-4" />
        <label for="{{ $prefix }}relevant_gross" class="text-sm">Brutto-relevant</label>
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" id="{{ $prefix }}relevant_social_sec" wire:model.defer="{{ $prefix }}relevant_social_sec" class="w-4 h-4" />
        <label for="{{ $prefix }}relevant_social_sec" class="text-sm">Sozialversicherungspflichtig</label>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="flex items-center gap-2">
        <input type="checkbox" id="{{ $prefix }}relevant_tax" wire:model.defer="{{ $prefix }}relevant_tax" class="w-4 h-4" />
        <label for="{{ $prefix }}relevant_tax" class="text-sm">Steuerpflichtig</label>
    </div>
    <x-ui-input-select :name="$prefix.'addition_deduction'" label="Art" wire:model.defer="{{ $prefix }}addition_deduction" :options="[
        'addition' => 'Zuschlag',
        'deduction' => 'Abzug',
        'neutral' => 'Neutral'
    ]" />
</div>
<x-ui-input-text :name="$prefix.'default_rate'" label="Standard-Satz" type="number" step="0.0001" wire:model.defer="{{ $prefix }}default_rate" placeholder="z.B. 15.50" />
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <x-ui-input-text :name="$prefix.'valid_from'" label="Gültig ab" type="date" wire:model.defer="{{ $prefix }}valid_from" />
    <x-ui-input-text :name="$prefix.'valid_to'" label="Gültig bis" type="date" wire:model.defer="{{ $prefix }}valid_to" />
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <x-ui-input-select 
        :name="$prefix.'debit_finance_account_id'" 
        label="Soll-Konto" 
        wire:model.defer="{{ $prefix }}debit_finance_account_id" 
        :options="$financeAccounts ?? []"
        placeholder="Konto auswählen..."
    />
    <x-ui-input-select 
        :name="$prefix.'credit_finance_account_id'" 
        label="Haben-Konto" 
        wire:model.defer="{{ $prefix }}credit_finance_account_id" 
        :options="$financeAccounts ?? []"
        placeholder="Konto auswählen..."
    />
</div>
<x-ui-input-text :name="$prefix.'display_group'" label="Anzeigegruppe" wire:model.defer="{{ $prefix }}display_group" placeholder="Grundlohn, Zulagen …" />
<x-ui-input-textarea :name="$prefix.'description'" label="Beschreibung" rows="3" wire:model.defer="{{ $prefix }}description" />
<div class="flex items-center gap-2">
    <input type="checkbox" id="{{ $prefix }}is_active" wire:model.defer="{{ $prefix }}is_active" class="w-4 h-4" />
    <label for="{{ $prefix }}is_active" class="text-sm">Aktiv</label>
</div>

