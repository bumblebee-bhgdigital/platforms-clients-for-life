<?php

namespace Platform\ClientsForLife\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class CreateClientsForLifeTemplateTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients-for-life.templates.POST';
    }

    public function getDescription(): string
    {
        return 'POST /clients-for-life/templates - Legt eine Vorlage für Clients-for-Life-Instanzen an (z. B. "Standard", "Key Account"). Pflicht: name.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name der Vorlage (ERFORDERLICH).'],
                'description' => ['type' => 'string', 'description' => 'Beschreibung der Vorlage.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (! Gate::forUser($context->user)->allows('create', ClientsForLifeTemplate::class)) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Vorlagen anlegen.');
            }

            $template = app(ClientsForLifeService::class)->createTemplate($context->user, $team, $arguments);

            return ToolResult::success([
                'template' => $this->templateToArray($template),
                'message' => "Vorlage '{$template->name}' (#{$template->id}) angelegt.",
            ]);
        } catch (ClientsForLifeException $e) {
            return ToolResult::error($e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('action', ['templates', 'create'], false, 'write', false);
    }
}
