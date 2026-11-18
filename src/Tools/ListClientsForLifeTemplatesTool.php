<?php

namespace Platform\ClientsForLife\Tools;

use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\ClientsForLife\Tools\Concerns\HandlesClientsForLife;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class ListClientsForLifeTemplatesTool implements ToolContract, ToolMetadataContract
{
    use HandlesClientsForLife;

    public function getName(): string
    {
        return 'clients_for_life_templates.GET';
    }

    public function getDescription(): string
    {
        return 'GET /clients-for-life/templates - Listet Vorlagen (z. B. Standard, Key Account), aus denen Clients-for-Life-Instanzen entstehen. Einzelne Vorlage: id=<id>. Filter: search, limit/offset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelne Vorlage anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Name/Beschreibung.'],
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

            if (! empty($arguments['id'])) {
                $template = ClientsForLifeTemplate::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $template) {
                    return ToolResult::error('NOT_FOUND', 'Vorlage nicht gefunden.');
                }
                if ($denied = $this->authorizeModel($context, 'view', $template)) {
                    return $denied;
                }

                return ToolResult::success(['template' => $this->templateToArray($template)]);
            }

            $query = ClientsForLifeTemplate::query()->where('team_id', $team->id)->orderBy('name');
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term));
            }

            $total = (clone $query)->count();
            $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);
            $offset = max((int) ($arguments['offset'] ?? 0), 0);

            return ToolResult::success([
                'templates' => $query->skip($offset)->take($limit)->get()->map(fn ($t) => $this->templateToArray($t))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Vorlagen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('query', ['templates', 'list'], true, 'safe', true);
    }
}
