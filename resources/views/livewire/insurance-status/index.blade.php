<div>
    <x-ui-page-header title="Versicherungsstatus">
        <x-slot:actions>
            <x-ui-button variant="primary" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span class="ml-1">Neu</span>
            </x-ui-button>
        </x-slot:actions>
    </x-ui-page-header>

    <div class="mt-4">
        <x-ui-table>
            <x-slot name="header">
                <x-ui-table-header>Code</x-ui-table-header>
                <x-ui-table-header>Name</x-ui-table-header>
                <x-ui-table-header>Status</x-ui-table-header>
                <x-ui-table-header class="text-right">Aktionen</x-ui-table-header>
            </x-slot>

            @foreach($items as $item)
                <x-ui-table-row>
                    <x-ui-table-cell>{{ $item->code }}</x-ui-table-cell>
                    <x-ui-table-cell>{{ $item->name }}</x-ui-table-cell>
                    <x-ui-table-cell>
                            <x-ui-badge variant="{{ $item->is_active ? 'success' : 'secondary' }}">
                                {{ $item->is_active ? 'aktiv' : 'inaktiv' }}
                            </x-ui-badge>
                    </x-ui-table-cell>
                    <x-ui-table-cell class="text-right">
                            <x-ui-button variant="secondary" size="sm" wire:click="openEditModal({{ $item->id }})">Bearbeiten</x-ui-button>
                            <x-ui-button variant="danger" size="sm" wire:click="delete({{ $item->id }})">Löschen</x-ui-button>
                    </x-ui-table-cell>
                </x-ui-table-row>
            @endforeach
        </x-ui-table>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>

    <x-ui-modal wire:model="showCreateModal">
        <x-slot:title>Neuen Versicherungsstatus anlegen</x-slot:title>
        <x-slot:content>
            <x-ui-input-text label="Code" wire:model.defer="code" />
            <div class="mt-3">
                <x-ui-input-text label="Name" wire:model.defer="name" />
            </div>
            <div class="mt-3">
                <x-ui-input-textarea label="Beschreibung" wire:model.defer="description" />
            </div>
            <div class="mt-3">
                <x-ui-input-checkbox wire:model.defer="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
            </div>
        </x-slot:content>
        <x-slot:footer>
            <x-ui-button variant="secondary" wire:click="closeModals">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </x-slot:footer>
    </x-ui-modal>

    <x-ui-modal wire:model="showEditModal">
        <x-slot:title>Versicherungsstatus bearbeiten</x-slot:title>
        <x-slot:content>
            <x-ui-input-text label="Code" wire:model.defer="code" />
            <div class="mt-3">
                <x-ui-input-text label="Name" wire:model.defer="name" />
            </div>
            <div class="mt-3">
                <x-ui-input-textarea label="Beschreibung" wire:model.defer="description" />
            </div>
            <div class="mt-3">
                <x-ui-input-checkbox wire:model.defer="is_active" checked-label="Aktiv" unchecked-label="Inaktiv" />
            </div>
        </x-slot:content>
        <x-slot:footer>
            <x-ui-button variant="secondary" wire:click="closeModals">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </x-slot:footer>
    </x-ui-modal>
</div>


