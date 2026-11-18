<?php

/**
 * Modul-Sidebar: Clients for Life nach Knoten (Organisations-Entity) gruppiert,
 * nicht verknüpfte Instanzen separat. Aufbau 1:1 wie die Planner-Sidebar (Entity-Baum
 * über EntityDimensionBridge + EntityAncestorService), zusätzlich mit Verknüpfen/Umhängen/Lösen.
 */

namespace Platform\ClientsForLife\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\ClientsForLife\Livewire\Concerns\LinksClientsToNodes;
use Platform\ClientsForLife\Livewire\Concerns\ResolvesTeamFamily;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityAncestorService;
use Platform\Organization\Services\EntityDimensionBridge;

class Sidebar extends Component
{
    use ResolvesTeamFamily, LinksClientsToNodes;

    /** Sidebar neu rendern, wenn irgendwo eine Instanz verknüpft/gelöst/angelegt wurde. */
    #[On('updateSidebar')]
    public function updateSidebar(): void
    {
    }

    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam?->id;

        $empty = [
            'entityTypeGroups' => collect(),
            'unlinkedClients' => collect(),
            'linkCandidates' => [],
            'linkedClientIds' => [],
        ];

        if (! $user || ! $teamId) {
            return view('clients-for-life::livewire.sidebar', $empty);
        }

        // Archivierte Instanzen bleiben als Historie über Übersicht/Detail erreichbar, nicht in der Navigation.
        $clients = ClientsForLife::query()
            ->whereIn('team_id', $this->relevantTeamIds($user))
            ->visibleTo($user)
            ->where('status', '!=', \Platform\ClientsForLife\Enums\ClientsForLifeStatus::ARCHIVED->value)
            ->orderBy('title')
            ->get();

        // Knoten-Verknüpfungen (DimensionLink, Alias 'clients_for_life')
        $entityClientMap = []; // entity_id => [client_id]
        $linkedClientIds = [];
        $links = EntityDimensionBridge::linksForLinkables([ClientsForLife::MORPH_ALIAS], $clients->pluck('id')->all());
        foreach ($links as $link) {
            if (! $link->entity_id) {
                continue;
            }
            $entityClientMap[$link->entity_id][] = $link->linkable_id;
            $linkedClientIds[] = $link->linkable_id;
        }
        foreach ($entityClientMap as $entityId => $ids) {
            $entityClientMap[$entityId] = array_values(array_unique($ids));
        }
        $linkedClientIds = array_values(array_unique($linkedClientIds));

        // Aufwärts-Traversierung: Tree-Parents (nur struktureller Baum, kein engagement_with-Channel)
        $ancestorService = new EntityAncestorService();
        $channels = [];
        foreach ($ancestorService->expandEntitiesWithAncestors(array_keys($entityClientMap), $channels) as $entityId) {
            $entityClientMap[$entityId] ??= [];
        }

        $entityTypeGroups = collect();
        if ($entityClientMap !== []) {
            $entities = OrganizationEntity::with('type')->whereIn('id', array_keys($entityClientMap))->get()->keyBy('id');
            $hierarchy = $ancestorService->buildParentChildrenMap($entities, $channels);
            $childrenMap = $hierarchy['parent_to_children'];

            $buildTree = function (int $entityId) use (&$buildTree, $entities, $childrenMap, $entityClientMap, $clients): ?array {
                $entity = $entities->get($entityId);
                if (! $entity) {
                    return null;
                }

                $childNodes = collect($childrenMap[$entityId] ?? [])->map(fn ($id) => $buildTree($id))->filter();
                $childrenByType = $childNodes
                    ->groupBy(fn ($c) => $c['type_id'])
                    ->map(function ($group) use ($entities) {
                        $first = $group->first();
                        $type = $entities->get($first['entity_id'])?->type;

                        return [
                            'type_id' => $first['type_id'],
                            'type_name' => $type?->name ?? 'Sonstige',
                            'type_icon' => $type?->icon ?? null,
                            'sort_order' => $type?->sort_order ?? 999,
                            'children' => $group->sortBy('entity_name')->values(),
                        ];
                    })
                    ->sortBy('sort_order')->values();

                $nodeClients = collect($entityClientMap[$entityId] ?? [])
                    ->map(fn ($id) => $clients->firstWhere('id', $id))->filter()
                    // aktive zuerst, dann Entwurf
                    ->sortBy(fn ($b) => [['active' => 0, 'draft' => 1][$b->status->value] ?? 2, $b->title])
                    ->values();

                $total = $nodeClients->count() + $childNodes->sum('total_clients');
                if ($total === 0) {
                    return null;
                }

                return [
                    'entity_id' => $entityId,
                    'entity_name' => $entity->name,
                    'type_id' => $entity->type?->id,
                    'clients' => $nodeClients,
                    'children_by_type' => $childrenByType,
                    'total_clients' => $total,
                ];
            };

            $grouped = [];
            foreach ($hierarchy['roots'] as $entityId) {
                $entity = $entities->get($entityId);
                if (! $entity || ! $entity->type || ! ($tree = $buildTree($entityId))) {
                    continue;
                }
                $typeId = $entity->type->id;
                $grouped[$typeId] ??= [
                    'type_id' => $typeId,
                    'type_name' => $entity->type->name,
                    'type_icon' => $entity->type->icon,
                    'sort_order' => $entity->type->sort_order ?? 999,
                    'entities' => [],
                ];
                $grouped[$typeId]['entities'][] = $tree;
            }

            $entityTypeGroups = collect($grouped)->sortBy('sort_order')
                ->map(function ($g) {
                    $g['entities'] = collect($g['entities'])->sortBy('entity_name')->values();

                    return $g;
                })->values();
        }

        return view('clients-for-life::livewire.sidebar', [
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedClients' => $clients->reject(fn ($b) => in_array($b->id, $linkedClientIds, true))->values(),
            'linkCandidates' => $this->linkCandidates(),
            'linkedClientIds' => $linkedClientIds,
        ]);
    }
}
