<?php

namespace Platform\ClientsForLife\Tools;

use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class DeleteClientsForLifeTemplateTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients_for_life_templates.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /clients-for-life/templates/{id} - Löscht eine Vorlage (Soft-Delete). Pflicht: id. Bereits erzeugte Instanzen behalten ihre Vorlagen-Referenz.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der Vorlage (ERFORDERLICH).'],
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
            if ($denied = $this->authorizeModel($context, 'delete', $template)) {
                return $denied;
            }

            $name = $template->name;
            $id = $template->id;
            app(ClientsForLifeService::class)->deleteTemplate($template);

            return ToolResult::success(['id' => $id, 'name' => $name, 'message' => "Vorlage '{$name}' (#{$id}) gelöscht (Soft-Delete)."]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('action', ['templates', 'delete'], false, 'destructive', false);
    }
}
