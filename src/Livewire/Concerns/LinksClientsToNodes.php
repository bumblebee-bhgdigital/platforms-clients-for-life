<?php

namespace Platform\ClientsForLife\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Verknüpfen / Umhängen / Lösen einer Instanz am Knoten aus der UI (Sidebar, Detailseite).
 *
 * Nutzt die Dimension Bridge über ClientsForLifeService (analog ProjectsCleanup::replaceLinks im Planner),
 * prüft Schreibrecht auf die Instanz (Policy) UND auf den Zielknoten (Core-Graph, writableEntityIds) und
 * dispatcht anschließend das UI-Event `updateSidebar`, auf das die Sidebar hört.
 * Der Service löst dabei ggf. nichts weiter (keine Ablöse-Regel).
 */
trait LinksClientsToNodes
{
    public ?int $linkingClientId = null;
    public string $linkSearch = '';

    public function startLinking(int $clientId): void
    {
        Gate::authorize('update', ClientsForLife::findOrFail($clientId));
        $this->linkingClientId = $clientId;
        $this->linkSearch = '';
    }

    public function cancelLinking(): void
    {
        $this->linkingClientId = null;
        $this->linkSearch = '';
    }

    /** Verknüpfen (Instanz ohne Knoten) bzw. Umhängen (Instanz mit Knoten) auf $entityId. */
    public function linkTo(int $entityId): void
    {
        $client = ClientsForLife::findOrFail((int) $this->linkingClientId);
        Gate::authorize('update', $client);

        if (! in_array($entityId, (new DimensionLinkAuthorizer())->writableEntityIds(auth()->user()), true)) {
            abort(403, 'Kein Schreibrecht auf diesen Knoten.');
        }

        $service = app(ClientsForLifeService::class);
        $service->nodeIds($client) === [] ? $service->link($client, $entityId) : $service->relink($client, $entityId);

        $this->afterLinkChange();
    }

    public function unlinkClient(int $clientId): void
    {
        $client = ClientsForLife::findOrFail($clientId);
        Gate::authorize('update', $client);

        app(ClientsForLifeService::class)->unlink($client);

        $this->afterLinkChange();
    }

    protected function afterLinkChange(): void
    {
        $this->cancelLinking();
        $this->dispatch('updateSidebar');
    }

    /**
     * Kandidaten für den Knoten-Picker: Entities, auf die der User schreiben darf, optional gefiltert.
     *
     * @return array<int, array{id:int,name:string,type:?string}>
     */
    protected function linkCandidates(): array
    {
        $user = auth()->user();
        if (! $user || ! $this->linkingClientId) {
            return [];
        }

        $writable = (new DimensionLinkAuthorizer())->writableEntityIds($user);
        if ($writable === []) {
            return [];
        }

        $search = trim($this->linkSearch);

        return OrganizationEntity::query()
            ->with('type:id,name')
            ->whereIn('id', $writable)
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit($search !== '' ? 20 : 10)
            ->get(['id', 'name', 'entity_type_id'])
            ->map(fn ($e) => ['id' => (int) $e->id, 'name' => $e->name, 'type' => $e->type?->name])
            ->all();
    }
}
