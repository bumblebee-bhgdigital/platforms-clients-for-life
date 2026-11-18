<?php

namespace Platform\ClientsForLife\Tools;

use Platform\ClientsForLife\Enums\ClientsForLifeStatus;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\EntityDimensionBridge;

class ListClientsForLifeTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients_for_life.GET';
    }

    public function getDescription(): string
    {
        return 'GET /clients-for-life - Listet Clients-for-Life-Instanzen (eine pro Kunde) des Teams (nur für den User sichtbare). Einzelne Instanz: id=<id>. '
            . 'Filter: entity_id=<Kunden-Knoten> (nur Instanzen an diesem Knoten), unlinked=true (Instanzen ohne Knoten), template_id, status, search, limit/offset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelne Instanz anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: nur Instanzen, die an diesem Kunden-Knoten (Organisations-Entity) hängen.'],
                'template_id' => ['type' => 'integer', 'description' => 'Optional: nur Instanzen, die aus dieser Vorlage entstanden sind.'],
                'status' => ['type' => 'string', 'enum' => ClientsForLifeStatus::values(), 'description' => 'Optional: Status-Filter.'],
                'unlinked' => ['type' => 'boolean', 'description' => 'Optional: nur Instanzen ohne Knoten-Verknüpfung.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Titel/Beschreibung.'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 50, max 200).'],
                'offset' => ['type' => 'integer', 'description' => 'Optional: Offset.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $service = app(ClientsForLifeService::class);

            if (! empty($arguments['id'])) {
                $client = ClientsForLife::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $client) {
                    return ToolResult::error('NOT_FOUND', 'Clients-for-Life-Instanz nicht gefunden.');
                }
                if ($denied = $this->authorizeModel($context, 'view', $client)) {
                    return $denied;
                }

                return ToolResult::success(['client' => $this->clientToArray($client)]);
            }

            $query = ClientsForLife::query()->where('team_id', $team->id)->visibleTo($context->user);

            if (! empty($arguments['status'])) {
                $status = ClientsForLifeStatus::tryFrom((string) $arguments['status']);
                if (! $status) {
                    return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', ClientsForLifeStatus::values()) . ' sein.');
                }
                $query->status($status);
            }

            if (! empty($arguments['template_id'])) {
                $query->where('template_id', (int) $arguments['template_id']);
            }

            if (! empty($arguments['entity_id'])) {
                $ids = $service->clientsForNode((int) $arguments['entity_id'])->pluck('id')->all();
                $query->whereIn('id', $ids ?: [0]);
            }

            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
            }

            $clients = $query->orderBy('title')->get();

            if (! empty($arguments['unlinked'])) {
                $linked = EntityDimensionBridge::linksForLinkables(
                    [ClientsForLife::MORPH_ALIAS], $clients->pluck('id')->all(), false
                )->pluck('linkable_id')->all();
                $clients = $clients->reject(fn ($c) => in_array($c->id, $linked, true))->values();
            }

            $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);
            $offset = max((int) ($arguments['offset'] ?? 0), 0);
            $total = $clients->count();
            $page = $clients->slice($offset, $limit)->values();

            return ToolResult::success([
                'clients' => $page->map(fn ($c) => $this->clientToArray($c))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Clients-for-Life-Instanzen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('query', ['list'], true, 'safe', true);
    }
}
