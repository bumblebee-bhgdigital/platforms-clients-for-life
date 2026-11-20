<?php

namespace Platform\ClientsForLife\Tools;

use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class DeleteClientsForLifeTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients-for-life.clients.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /clients-for-life/{id} - Löscht eine Clients-for-Life-Instanz (Soft-Delete, wiederherstellbar). Pflicht: id. '
            . 'Zum Beenden einer Kundenbeziehung ohne Löschen stattdessen clients-for-life.clients.PUT mit status=archived nutzen (Historie bleibt sichtbar).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der Instanz (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
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
            $client = ClientsForLife::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $client) {
                return ToolResult::error('NOT_FOUND', 'Clients-for-Life-Instanz nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'delete', $client)) {
                return $denied;
            }

            $title = $client->title;
            $id = $client->id;
            app(ClientsForLifeService::class)->delete($client);

            return ToolResult::success([
                'id' => $id,
                'title' => $title,
                'message' => "Clients-for-Life-Instanz '{$title}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('action', ['delete'], false, 'destructive', false);
    }
}
