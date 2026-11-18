{{--
    Übersicht aller Clients-for-Life-Instanzen (nx-Design-System).
    Shell: x-ui-page*; Inhalt: x-nx-* + var(--nx-*). Datenform "gleichförmige Datensätze" -> Tabelle.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Clients for Life" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Clients for Life', 'icon' => 'heart'],
        ]">
            @can('create', \Platform\ClientsForLife\Models\ClientsForLife::class)
                <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neu</span>
                </x-nx-button>
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-stat-grid :cols="4">
            <x-nx-stat label="Instanzen gesamt" :value="$stats['total']" icon="heroicon-o-heart" />
            <x-nx-stat label="Aktiv" :value="$stats['active']" icon="heroicon-o-check-circle" accent="var(--nx-success)" />
            <x-nx-stat label="Entwurf" :value="$stats['draft']" icon="heroicon-o-pencil-square" />
            <x-nx-stat label="Unverknüpft" :value="$stats['unlinked']" icon="heroicon-o-link-slash" accent="var(--nx-warning)" />
        </x-nx-stat-grid>

        <x-nx-section icon="heroicon-o-list-bullet" title="Alle Instanzen" :hint="$clients->count()"
                      description="Eine Instanz pro Kunde: gelebte Beziehung, entstanden aus einer Vorlage und am Kunden-Knoten der Organisation verortet.">
            <x-slot name="action">
                <x-nx-input-select name="statusFilter" size="sm" :options="$statusOptions" nullable="true" nullLabel="Alle Status" wire:model.live="statusFilter" />
            </x-slot>

            @if($clients->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-heart">
                        Noch keine Clients-for-Life-Instanzen{{ $statusFilter !== '' ? ' in diesem Status' : '' }}.
                        @can('create', \Platform\ClientsForLife\Models\ClientsForLife::class)
                            <x-slot name="action">
                                <x-nx-button variant="secondary" size="sm" wire:click="openCreate">Erste Instanz anlegen</x-nx-button>
                            </x-slot>
                        @endcan
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Titel</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Kunden-Knoten</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Vorlage</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Verantwortlich</x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($clients as $client)
                                <x-nx-table-row wire:key="client-row-{{ $client->id }}" :href="route('clients-for-life.clients.show', $client)">
                                    <x-nx-table-cell>
                                        {{ $client->title }}
                                        @if($multiTeam && $client->team)
                                            <span class="ml-1 text-xs text-[color:var(--nx-muted)]">{{ $client->team->name }}</span>
                                        @endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        @forelse($nodesByClient[$client->id] ?? [] as $node)
                                            <span class="text-xs">{{ $node['name'] }}</span>@if(! $loop->last), @endif
                                        @empty
                                            <span class="text-xs text-[color:var(--nx-muted)]">Unverknüpft</span>
                                        @endforelse
                                    </x-nx-table-cell>
                                    <x-nx-table-cell><x-nx-badge :variant="$client->status->badgeVariant()" dot>{{ $client->status->label() }}</x-nx-badge></x-nx-table-cell>
                                    <x-nx-table-cell>{{ $client->template?->name ?? '–' }}</x-nx-table-cell>
                                    <x-nx-table-cell>{{ $client->owner?->name ?? '–' }}</x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    {{-- Die Modul-Sidebar (Instanzen nach Kunden-Knoten) kommt aus clients-for-life.sidebar (Shell), wie im Planner. --}}

    {{-- Anlegen --}}
    <x-nx-modal wire:model="showCreate" size="md">
        <x-slot name="header">Clients-for-Life-Instanz anlegen</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.title" label="Titel (Kunde)" wire:model="form.title" required />
            <x-nx-input-select name="form.template_id" label="Vorlage" :options="$templateOptions" nullable="true" nullLabel="Ohne Vorlage" wire:model="form.template_id" />
            <x-nx-input-textarea name="form.description" label="Beschreibung" wire:model="form.description" rows="3" />
            <p class="text-xs text-[color:var(--nx-muted)]">Die Verknüpfung mit dem Kunden-Knoten erfolgt anschließend auf der Detailseite oder in der Sidebar.</p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="create">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
