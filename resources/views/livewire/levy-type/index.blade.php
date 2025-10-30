<div>
    <x-ui-page-header title="Umlagearten (U1/U2/Insolvenz)">
        <x-slot:actions>
            <x-ui-button variant="primary" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span class="ml-1">Neu</span>
            </x-ui-button>
        </x-slot:actions>
    </x-ui-page-header>

    <div class="mt-4">
        <x-ui-table>
            <x-slot:head>
                <x-ui-table.th>Code</x-ui-table.th>
                <x-ui-table.th>Name</x-ui-table.th>
                <x-ui-table.th>Status</x-ui-table.th>
                <x-ui-table.th class="text-right">Aktionen</x-ui-table.th>
            </x-slot:head>

            <x-slot:body>
                @foreach($items as $item)
                    <x-ui-table.tr>
                        <x-ui-table.td>{{ $item->code }}</x-ui-table.td>
                        <x-ui-table.td>{{ $item->name }}</x-ui-table.td>
                        <x-ui-table.td>
                            <x-ui-badge variant="{{ $item->is_active ? 'success' : 'secondary' }}">
                                {{ $item->is_active ? 'aktiv' : 'inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table.td>
                        <x-ui-table.td class="text-right">
                            <x-ui-button variant="secondary" size="sm" wire:click="openEditModal({{ $item->id }})">Bearbeiten</x-ui-button>
                            <x-ui-button variant="danger" size="sm" wire:click="delete({{ $item->id }})">Löschen</x-ui-button>
                        </x-ui-table.td>
                    </x-ui-table.tr>
                @endforeach
            </x-slot:body>
        </x-ui-table>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>

    <x-ui-modal wire:model="showCreateModal">
        <x-slot:title>Neue Umlageart anlegen</x-slot:title>
        <x-slot:content>
            <x-ui-form.group label="Code">
                <x-ui-input-text wire:model.defer="code" />
            </x-ui-form.group>
            <x-ui-form.group label="Name" class="mt-3">
                <x-ui-input-text wire:model.defer="name" />
            </x-ui-form.group>
            <x-ui-form.group label="Beschreibung" class="mt-3">
                <x-ui-input-textarea wire:model.defer="description" />
            </x-ui-form.group>
            <x-ui-form.group label="Aktiv" class="mt-3">
                <x-ui-input-checkbox wire:model.defer="is_active" />
            </x-ui-form.group>
        </x-slot:content>
        <x-slot:footer>
            <x-ui-button.secondary wire:click="closeModals">Abbrechen</x-ui-button.secondary>
            <x-ui-button.primary wire:click="save">Speichern</x-ui-button.primary>
        </x-slot:footer>
    </x-ui-modal>

    <x-ui-modal wire:model="showEditModal">
        <x-slot:title>Umlageart bearbeiten</x-slot:title>
        <x-slot:content>
            <x-ui-form.group label="Code">
                <x-ui-input-text wire:model.defer="code" />
            </x-ui-form.group>
            <x-ui-form.group label="Name" class="mt-3">
                <x-ui-input-text wire:model.defer="name" />
            </x-ui-form.group>
            <x-ui-form.group label="Beschreibung" class="mt-3">
                <x-ui-input-textarea wire:model.defer="description" />
            </x-ui-form.group>
            <x-ui-form.group label="Aktiv" class="mt-3">
                <x-ui-input-checkbox wire:model.defer="is_active" />
            </x-ui-form.group>
        </x-slot:content>
        <x-slot:footer>
            <x-ui-button.secondary wire:click="closeModals">Abbrechen</x-ui-button.secondary>
            <x-ui-button.primary wire:click="save">Speichern</x-ui-button.primary>
        </x-slot:footer>
    </x-ui-modal>
</div>


