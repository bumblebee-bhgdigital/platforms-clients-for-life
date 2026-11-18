{{--
    Detailseite Clients-for-Life-Instanz (nx-Design-System): Eigenschaften + Platzhalter-Bereiche.
    Beziehungsnetz / Touchpoints / Gesundheitscheck / Jahresmeeting folgen in eigenen Boards und docken hier als Bereiche an.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$client->title" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Clients for Life', 'href' => route('clients-for-life.dashboard'), 'icon' => 'heart'],
            ['label' => $client->title],
        ]">
            @can('update', $client)
                {{-- ≥2 Aktionen -> ein Dropdown --}}
                <x-nx-dropdown label="Aktionen">
                    <x-nx-dropdown-item wire:click="openEdit">@svg('heroicon-o-pencil-square', 'w-4 h-4') Bearbeiten</x-nx-dropdown-item>
                    @if($client->status !== \Platform\ClientsForLife\Enums\ClientsForLifeStatus::ACTIVE)
                        <x-nx-dropdown-item wire:click="activate">@svg('heroicon-o-check-circle', 'w-4 h-4') Aktivieren</x-nx-dropdown-item>
                    @endif
                    @if($client->status !== \Platform\ClientsForLife\Enums\ClientsForLifeStatus::ARCHIVED)
                        <x-nx-dropdown-item wire:click="archive" wire:confirm="Instanz archivieren?">@svg('heroicon-o-archive-box', 'w-4 h-4') Archivieren</x-nx-dropdown-item>
                    @endif
                    @can('delete', $client)
                        <x-nx-dropdown-divider />
                        <x-nx-dropdown-item variant="danger" wire:click="deleteClient" wire:confirm="Instanz löschen? (Soft-Delete)">@svg('heroicon-o-trash', 'w-4 h-4') Löschen</x-nx-dropdown-item>
                    @endcan
                </x-nx-dropdown>
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @if(session('error'))
            <x-nx-callout variant="danger" icon="heroicon-o-exclamation-triangle" title="Fehler">{{ session('error') }}</x-nx-callout>
        @endif

        {{-- Kopf --}}
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="m-0 text-xl font-semibold text-[color:var(--nx-text)] truncate">{{ $client->title }}</h1>
                @if($client->description)
                    <p class="mt-1 mb-0 text-sm text-[color:var(--nx-muted)] whitespace-pre-line">{{ $client->description }}</p>
                @endif
            </div>
            <x-nx-badge :variant="$client->status->badgeVariant()" dot>{{ $client->status->label() }}</x-nx-badge>
        </div>

        {{-- Eigenschaften --}}
        <x-nx-card>
            <x-nx-property-row icon="heroicon-o-building-office" label="Kunden-Knoten">
                <div class="flex flex-wrap items-center gap-2">
                    @forelse($nodes as $node)
                        <a href="{{ route('organization.entities.show', $node) }}" wire:navigate class="inline-flex items-center gap-1 text-sm hover:underline">
                            {{ $node->name }}
                            @if($node->type)<span class="text-xs text-[color:var(--nx-muted)]">({{ $node->type->name }})</span>@endif
                        </a>
                    @empty
                        <span class="text-[color:var(--nx-muted)]">Nicht verknüpft</span>
                    @endforelse

                    @can('update', $client)
                        <button type="button" wire:click="startLinking({{ $client->id }})" class="text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] underline">
                            {{ $nodes->isEmpty() ? 'Mit Knoten verknüpfen' : 'Umhängen' }}
                        </button>
                        @if($nodes->isNotEmpty())
                            <button type="button" wire:click="unlinkClient({{ $client->id }})" wire:confirm="Verknüpfung mit dem Knoten lösen?" class="text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)] underline">Lösen</button>
                        @endif
                    @endcan
                </div>

                @if($linkingClientId === $client->id)
                    <div class="mt-2 rounded-md border border-[color:var(--nx-line)] p-2">
                        <div class="flex items-center gap-2 mb-1">
                            <input type="text" wire:model.live.debounce.300ms="linkSearch" placeholder="Knoten suchen …"
                                   class="w-full rounded border border-[color:var(--nx-line)] bg-transparent px-2 py-1 text-sm text-[color:var(--nx-text)]" />
                            <x-nx-button variant="ghost" size="sm" wire:click="cancelLinking">Abbrechen</x-nx-button>
                        </div>
                        @forelse($linkCandidates as $candidate)
                            <button type="button" wire:key="cand-{{ $candidate['id'] }}" wire:click="linkTo({{ $candidate['id'] }})"
                                    class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-[color:var(--nx-hover)]">
                                <span class="truncate">{{ $candidate['name'] }}</span>
                                @if($candidate['type'])<span class="ml-auto text-xs text-[color:var(--nx-muted)]">{{ $candidate['type'] }}</span>@endif
                            </button>
                        @empty
                            <div class="px-2 py-1 text-sm text-[color:var(--nx-muted)]">Keine passenden Knoten (Schreibrecht erforderlich).</div>
                        @endforelse
                    </div>
                @endif
            </x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-document-duplicate" label="Vorlage">{{ $client->template?->name ?? '–' }}</x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-user" label="Verantwortlich">{{ $client->owner?->name ?? '–' }}</x-nx-property-row>
            <x-nx-property-row icon="heroicon-o-cpu-chip" label="Verantwortlicher Agent">{{ $client->responsibleAgent?->name ?? '–' }}</x-nx-property-row>
        </x-nx-card>

        {{-- Platzhalter-Bereiche (folgen in eigenen Boards) --}}
        <x-nx-section icon="heroicon-o-share" title="Beziehungsnetz" description="Ansprechpartner und Beziehungen beim Kunden.">
            <x-nx-card><x-nx-empty icon="heroicon-o-share">Noch kein Beziehungsnetz — dieser Bereich folgt in einem eigenen Board.</x-nx-empty></x-nx-card>
        </x-nx-section>

        <x-nx-section icon="heroicon-o-chat-bubble-left-right" title="Touchpoints" description="Geplante und erfolgte Kontaktpunkte mit dem Kunden.">
            <x-nx-card><x-nx-empty icon="heroicon-o-chat-bubble-left-right">Noch keine Touchpoints — dieser Bereich folgt in einem eigenen Board.</x-nx-empty></x-nx-card>
        </x-nx-section>

        <x-nx-section icon="heroicon-o-heart" title="Gesundheitscheck" description="Zustand der Kundenbeziehung, regelmäßig bewertet.">
            <x-nx-card><x-nx-empty icon="heroicon-o-heart">Noch kein Gesundheitscheck — dieser Bereich folgt in einem eigenen Board.</x-nx-empty></x-nx-card>
        </x-nx-section>

        <x-nx-section icon="heroicon-o-calendar" title="Jahresmeeting" description="Jährliches Treffen zur Rückschau und Vorausplanung mit dem Kunden.">
            <x-nx-card><x-nx-empty icon="heroicon-o-calendar">Noch kein Jahresmeeting — dieser Bereich folgt in einem eigenen Board.</x-nx-empty></x-nx-card>
        </x-nx-section>
    </x-ui-page-container>

    {{-- Bearbeiten --}}
    <x-nx-modal wire:model="showEdit" size="md">
        <x-slot name="header">Clients-for-Life-Instanz bearbeiten</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.title" label="Titel" wire:model="form.title" required />
            <x-nx-input-textarea name="form.description" label="Beschreibung" wire:model="form.description" rows="3" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showEdit', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
