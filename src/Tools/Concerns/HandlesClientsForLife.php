<?php

namespace Platform\ClientsForLife\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationEntity;

/** Gemeinsame Helfer der clients_for_life.*-Tools (Team-Auflösung, Serialisierung, Knoten-Schreibrecht). */
trait HandlesClientsForLife
{
    /** @return array{team: Team|null, error: ToolResult|null} */
    protected function resolveTeam(array $arguments, ToolContext $context): array
    {
        if (! $context->user) {
            return ['team' => null, 'error' => ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.')];
        }

        $teamId = $arguments['team_id'] ?? null;
        if ($teamId === 0 || $teamId === '0') {
            $teamId = null;
        }
        $teamId = $teamId ?: $context->team?->id;
        if (! $teamId) {
            return ['team' => null, 'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext. Nutze "core.teams.GET".')];
        }

        $team = $context->user->teams()->where('teams.id', (int) $teamId)->first();
        if (! $team) {
            return ['team' => null, 'error' => ToolResult::error('ACCESS_DENIED', "Kein Zugriff auf Team {$teamId}. Nutze 'core.teams.GET'.")];
        }

        return ['team' => $team, 'error' => null];
    }

    /**
     * Darf der User an diesen Knoten verknüpfen? Gleiche Regel wie die Terminal-App "Verknüpfungen":
     * Entity muss im Schreib-Reichweitenbereich des Users liegen (Core-Graph, kein eigenes Rollensystem).
     */
    protected function canWriteEntity(ToolContext $context, int $entityId): bool
    {
        if (! class_exists(\Platform\Organization\Authz\DimensionLinkAuthorizer::class)) {
            return false;
        }

        return in_array($entityId, (new \Platform\Organization\Authz\DimensionLinkAuthorizer())->writableEntityIds($context->user), true)
            && OrganizationEntity::whereKey($entityId)->exists();
    }

    protected function authorizeModel(ToolContext $context, string $ability, ClientsForLife|ClientsForLifeTemplate $model): ?ToolResult
    {
        return Gate::forUser($context->user)->allows($ability, $model)
            ? null
            : ToolResult::error('ACCESS_DENIED', "Keine Berechtigung ({$ability}) für #{$model->id}.");
    }

    /** @return array<string,mixed> */
    protected function clientToArray(ClientsForLife $client): array
    {
        $nodeIds = app(ClientsForLifeService::class)->nodeIds($client);
        $names = $nodeIds === [] ? collect() : OrganizationEntity::whereIn('id', $nodeIds)->pluck('name', 'id');

        return [
            'id' => $client->id,
            'uuid' => $client->uuid,
            'team_id' => $client->team_id,
            'title' => $client->title,
            'description' => $client->description,
            'status' => $client->status?->value,
            'status_label' => $client->status?->label(),
            'template_id' => $client->template_id,
            'owner_user_id' => $client->owner_user_id,
            'responsible_agent_user_id' => $client->responsible_agent_user_id,
            'created_by_user_id' => $client->created_by_user_id,
            'nodes' => array_map(fn ($id) => ['entity_id' => $id, 'entity_name' => $names[$id] ?? null], $nodeIds),
            'created_at' => $client->created_at?->toIso8601String(),
            'updated_at' => $client->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    protected function templateToArray(ClientsForLifeTemplate $template): array
    {
        return [
            'id' => $template->id,
            'uuid' => $template->uuid,
            'team_id' => $template->team_id,
            'name' => $template->name,
            'description' => $template->description,
            'created_by_user_id' => $template->created_by_user_id,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /** Gemeinsame Property-Definitionen für POST/PUT der Instanz. */
    protected function clientFieldSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'description' => 'Titel der Instanz (üblicherweise der Kundenname).'],
            'description' => ['type' => 'string', 'description' => 'Beschreibung der Kundenbeziehung.'],
            'owner_user_id' => ['type' => 'integer', 'description' => 'Verantwortlicher (User-ID, Mitglied des Teams). Default beim Anlegen: aufrufender User.'],
            'responsible_agent_user_id' => ['type' => 'integer', 'description' => 'Verantwortlicher Agent (User-ID eines Agent-Users, type ai_user).'],
        ];
    }

    /** @return array<string,mixed> */
    protected function toolMetadata(string $category, array $tags, bool $readOnly, string $risk, bool $idempotent): array
    {
        return [
            'category' => $category,
            'tags' => array_merge(['clients-for-life'], $tags),
            'read_only' => $readOnly,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => $risk,
            'idempotent' => $idempotent,
        ];
    }
}
