{{--
    Vorlagen für Clients-for-Life-Instanzen (nx-Design-System).
    Grundgerüst: Name + Beschreibung; Struktur-Inhalte der Vorlage folgen in eigenen Boards.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Vorlagen" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Clients for Life', 'href' => route('clients-for-life.dashboard'), 'icon' => 'heart'],
            ['label' => 'Vorlagen'],
        ]">
            @can('create', \Platform\ClientsForLife\Models\ClientsForLifeTemplate::class)
                <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neu</span>
                </x-nx-button>
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-section icon="heroicon-o-document-duplicate" title="Vorlagen" :hint="$templates->count()"
                      description="Aus einer Vorlage (z. B. Standard, Key Account) entsteht je Kunde eine Clients-for-Life-Instanz.">
            @if($templates->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-document-duplicate">Noch keine Vorlagen.</x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Beschreibung</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Instanzen</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right"> </x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($templates as $template)
                                <x-nx-table-row wire:key="template-row-{{ $template->id }}">
                                    <x-nx-table-cell>{{ $template->name }}</x-nx-table-cell>
                                    <x-nx-table-cell>{{ $template->description ?: '–' }}</x-nx-table-cell>
                                    <x-nx-table-cell align="right">{{ $template->clients_count }}</x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        @can('update', $template)
                                            <button type="button" wire:click="openEdit({{ $template->id }})" class="text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] underline">Bearbeiten</button>
                                        @endcan
                                        @can('delete', $template)
                                            <button type="button" wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Vorlage löschen? Bestehende Instanzen bleiben erhalten." class="ml-2 text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)] underline">Löschen</button>
                                        @endcan
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    <x-nx-modal wire:model="showForm" size="md">
        <x-slot name="header">{{ $editingId ? 'Vorlage bearbeiten' : 'Vorlage anlegen' }}</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.name" label="Name" wire:model="form.name" required />
            <x-nx-input-textarea name="form.description" label="Beschreibung" wire:model="form.description" rows="3" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showForm', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
