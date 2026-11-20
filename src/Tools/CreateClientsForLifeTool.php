<?php

namespace Platform\ClientsForLife\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class CreateClientsForLifeTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients-for-life.clients.POST';
    }

    public function getDescription(): string
    {
        return 'POST /clients-for-life - Legt eine Clients-for-Life-Instanz (Kundenbeziehung) an, Status standardmäßig active. Pflicht: title. '
            . 'Optional template_id: Instanz entsteht aus dieser Vorlage. Optional entity_id: hängt die Instanz direkt an einen Kunden-Knoten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->clientFieldSchema(), [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Kunden-Knoten (Organisations-Entity). Nutze "organization.entities.GET".'],
                'template_id' => ['type' => 'integer', 'description' => 'Optional: Vorlage (clients-for-life.templates.GET).'],
                'status' => ['type' => 'string', 'enum' => ['draft', 'active'], 'description' => 'Optional: draft oder active (Default).'],
            ]),
            'required' => ['title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (! Gate::forUser($context->user)->allows('create', ClientsForLife::class)) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Clients-for-Life-Instanzen anlegen.');
            }

            $entityId = ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;
            if ($entityId && ! $this->canWriteEntity($context, $entityId)) {
                return ToolResult::error('ACCESS_DENIED', "Kein Schreibrecht auf Knoten {$entityId} (oder Knoten existiert nicht).");
            }

            $templateId = ! empty($arguments['template_id']) ? (int) $arguments['template_id'] : null;

            $client = app(ClientsForLifeService::class)->create($context->user, $team, $arguments, $entityId, $templateId);

            return ToolResult::success([
                'client' => $this->clientToArray($client),
                'message' => "Clients-for-Life-Instanz '{$client->title}' (#{$client->id}) angelegt (Status {$client->status->value}).",
            ]);
        } catch (ClientsForLifeException $e) {
            return ToolResult::error($e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('action', ['create'], false, 'write', false);
    }
}
