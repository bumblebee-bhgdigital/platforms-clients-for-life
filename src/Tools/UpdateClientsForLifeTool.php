<?php

namespace Platform\ClientsForLife\Tools;

use Platform\ClientsForLife\Enums\ClientsForLifeStatus;
use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class UpdateClientsForLifeTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients_for_life.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /clients-for-life/{id} - Ändert eine Clients-for-Life-Instanz. Pflicht: id. '
            . 'Felder: title, description, owner_user_id, responsible_agent_user_id. status=active (re)aktiviert, status=archived archiviert. '
            . 'Knoten: entity_id hängt die Instanz (um) an einen Kunden-Knoten, unlink=true löst die Verknüpfung (Instanz wird unverknüpft).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->clientFieldSchema(), [
                'id' => ['type' => 'integer', 'description' => 'ID der Instanz (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'status' => ['type' => 'string', 'enum' => ['active', 'archived'], 'description' => 'Optional: active = (re)aktivieren, archived = archivieren.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Instanz an diesen Kunden-Knoten umhängen (ersetzt bisherige Knoten).'],
                'unlink' => ['type' => 'boolean', 'description' => 'Optional: Knoten-Verknüpfung(en) lösen.'],
            ]),
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'id ist erforderlich.');
            }

            $client = ClientsForLife::query()->where('team_id', $team->id)->find((int) $arguments['id']);
            if (! $client) {
                return ToolResult::error('NOT_FOUND', 'Clients-for-Life-Instanz nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'update', $client)) {
                return $denied;
            }

            $status = null;
            if (! empty($arguments['status'])) {
                $status = ClientsForLifeStatus::tryFrom((string) $arguments['status']);
                if (! in_array($status, [ClientsForLifeStatus::ACTIVE, ClientsForLifeStatus::ARCHIVED], true)) {
                    return ToolResult::error('VALIDATION_ERROR', "status darf nur 'active' oder 'archived' sein.");
                }
            }

            $entityId = ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;
            if ($entityId && ! $this->canWriteEntity($context, $entityId)) {
                return ToolResult::error('ACCESS_DENIED', "Kein Schreibrecht auf Knoten {$entityId} (oder Knoten existiert nicht).");
            }

            $service = app(ClientsForLifeService::class);

            $fields = array_intersect_key($arguments, array_flip(['title', 'description', 'owner_user_id', 'responsible_agent_user_id']));
            if ($fields !== []) {
                $client = $service->update($client, $fields);
            }
            if (! empty($arguments['unlink'])) {
                $service->unlink($client);
            }
            if ($entityId) {
                $service->relink($client, $entityId);
            }
            if ($status === ClientsForLifeStatus::ACTIVE) {
                $service->activate($client);
            } elseif ($status === ClientsForLifeStatus::ARCHIVED) {
                $service->archive($client);
            }

            $client->refresh();

            return ToolResult::success([
                'client' => $this->clientToArray($client),
                'message' => "Clients-for-Life-Instanz #{$client->id} aktualisiert (Status {$client->status->value}).",
            ]);
        } catch (ClientsForLifeException $e) {
            return ToolResult::error($e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('action', ['update', 'archive', 'link'], false, 'write', true);
    }
}
