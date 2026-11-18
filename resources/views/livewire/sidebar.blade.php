{{--
    Sidebar View (nx-Design-System) — Clients for Life nach Knoten gruppiert, Unverknüpfte separat.
    Struktur wie planner::livewire.sidebar. Nur var(--nx-*) Tokens.
--}}

<div>
    <a x-show="!collapsed" href="{{ route('clients-for-life.dashboard') }}" wire:navigate
       class="block p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[var(--nx-line)] mb-2 hover:text-[var(--nx-accent)]">
        Clients for Life
    </a>

    {{-- Eingeklappt: Einstieg in die Übersicht --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-line)]">
        <a href="{{ route('clients-for-life.dashboard') }}" wire:navigate title="Clients for Life"
           class="flex items-center justify-center p-2 rounded-md {{ request()->routeIs('clients-for-life.*') ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)]' : 'text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}">
            @svg('heroicon-o-heart', 'w-5 h-5')
        </a>
    </div>

    <div class="mt-2" x-show="!collapsed">
        {{-- Knoten-Gruppen (Entity-Typ -> Baum -> Instanzen) --}}
        @foreach($entityTypeGroups as $typeGroup)
            <x-ui-sidebar-list wire:key="cfl-type-group-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                @foreach($typeGroup['entities'] as $entityNode)
                    @include('clients-for-life::livewire.partials.sidebar-entity-node', [
                        'node' => $entityNode,
                        'typeIcon' => $typeGroup['type_icon'] ?? null,
                        'linkingClientId' => $linkingClientId,
                        'linkCandidates' => $linkCandidates,
                    ])
                @endforeach
            </x-ui-sidebar-list>
        @endforeach

        {{-- Nicht verknüpfte Instanzen --}}
        @if($unlinkedClients->isNotEmpty())
            <x-ui-sidebar-list label="Unverknüpft">
                @foreach($unlinkedClients as $client)
                    @include('clients-for-life::livewire.partials.sidebar-client-row', ['client' => $client, 'linked' => false, 'linkingClientId' => $linkingClientId, 'linkCandidates' => $linkCandidates])
                @endforeach
            </x-ui-sidebar-list>
        @endif

        @if($entityTypeGroups->isEmpty() && $unlinkedClients->isEmpty())
            <div class="px-3 py-1 text-xs text-[var(--nx-muted)]">Keine Clients for Life</div>
        @endif

        {{-- Vorlagen: eigener Navigationsbereich --}}
        <x-ui-sidebar-list label="Vorlagen">
            <x-ui-sidebar-item :href="route('clients-for-life.templates.index')">
                @svg('heroicon-o-document-duplicate', 'w-4 h-4 text-[var(--nx-text)]')
                <span class="ml-2 text-sm">Vorlagen</span>
            </x-ui-sidebar-item>
        </x-ui-sidebar-list>

        {{-- Showcase (Gerüst-Beispiel) bleibt erreichbar --}}
        <x-ui-sidebar-list label="Entwicklung">
            <x-ui-sidebar-item :href="route('clients-for-life.showcase')">
                @svg('heroicon-o-swatch', 'w-4 h-4 text-[var(--nx-text)]')
                <span class="ml-2 text-sm">nx-Showcase</span>
            </x-ui-sidebar-item>
        </x-ui-sidebar-list>
    </div>
</div>
