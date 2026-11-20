<?php

namespace Platform\ClientsForLife\Tools;

use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class UpdateClientsForLifeTemplateTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients-for-life.templates.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /clients-for-life/templates/{id} - Ändert eine Vorlage (name, description). Pflicht: id. Bereits erzeugte Instanzen bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der Vorlage (ERFORDERLICH).'],
                'name' => ['type' => 'string', 'description' => 'Neuer Name.'],
                'description' => ['type' => 'string', 'description' => 'Neue Beschreibung.'],
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
            $template = ClientsForLifeTemplate::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $template) {
                return ToolResult::error('NOT_FOUND', 'Vorlage nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'update', $template)) {
                return $denied;
            }

            $template = app(ClientsForLifeService::class)->updateTemplate($template, array_intersect_key($arguments, array_flip(['name', 'description'])));

            return ToolResult::success([
                'template' => $this->templateToArray($template),
                'message' => "Vorlage #{$template->id} aktualisiert.",
            ]);
        } catch (ClientsForLifeException $e) {
            return ToolResult::error($e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('action', ['templates', 'update'], false, 'write', true);
    }
}
