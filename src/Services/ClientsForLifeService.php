<?php

namespace Platform\ClientsForLife\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Platform\ClientsForLife\Enums\ClientsForLifeStatus;
use Platform\ClientsForLife\Events\ClientsForLifeLinkChanged;
use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Organization\Services\EntityDimensionBridge;

/**
 * Use-Cases rund um Clients-for-Life-Instanzen und Vorlagen (analog ProjectCreationService/LifecycleService im Planner).
 *
 * Der Service autorisiert NICHT — Rechte werden an der Grenze (Livewire, MCP-Tool) per
 * Gate/ClientsForLifePolicy geprüft, wie im Planner. Er validiert nur Fachregeln.
 *
 * Unterschied zum Management Book: keine "genau eine aktive Instanz pro Knoten"-Regel — wie beim
 * Planner-Projekt dürfen mehrere Instanzen am selben Kunden-Knoten hängen.
 */
class ClientsForLifeService
{
    /** Felder, die create()/update() aus $data übernehmen. */
    private const FIELDS = ['title', 'description', 'owner_user_id', 'responsible_agent_user_id'];

    // ── Lesen ────────────────────────────────────────────────────

    public function find(int $id, bool $withTrashed = false): ?ClientsForLife
    {
        $query = ClientsForLife::query();
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    /**
     * Instanzen eines Teams (optional Status-Filter). Sichtbarkeit ($viewer) = Graph-Scope wie die UI.
     *
     * @param int|int[] $teamIds
     */
    public function listForTeams(int|array $teamIds, ?User $viewer = null, ?ClientsForLifeStatus $status = null): Collection
    {
        $query = ClientsForLife::query()
            ->whereIn('team_id', (array) $teamIds)
            ->orderBy('title');

        if ($viewer) {
            $query->visibleTo($viewer);
        }
        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /** Alle Instanzen eines Kunden-Knotens (jeder Status, ohne soft-gelöschte). */
    public function clientsForNode(int $entityId): Collection
    {
        $ids = $this->clientIdsAtNode($entityId);

        return $ids === [] ? new Collection() : ClientsForLife::query()->whereIn('id', $ids)->orderBy('title')->get();
    }

    /** Knoten (Entity-IDs), an denen die Instanz hängt. @return int[] */
    public function nodeIds(ClientsForLife $client): array
    {
        return EntityDimensionBridge::linksForLinkables([ClientsForLife::MORPH_ALIAS], [$client->id], false)
            ->pluck('entity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // ── Anlegen / Ändern / Archivieren ───────────────────────────

    /**
     * @param array<string,mixed> $data title (Pflicht, Default: Name des Kunden-Knotens ist Sache des Aufrufers) + Felder aus self::FIELDS, optional status (draft|active)
     * @param int|null $entityId optionaler Kunden-Knoten, an den die Instanz gleich gehängt wird
     * @param int|null $templateId optionale Vorlage; die Instanz entsteht daraus (Beschreibung wird übernommen, wenn keine angegeben)
     */
    public function create(User $creator, Team|int $team, array $data, ?int $entityId = null, ?int $templateId = null): ClientsForLife
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new ClientsForLifeException('Titel ist erforderlich.');
        }

        $status = ClientsForLifeStatus::ACTIVE;
        if (! empty($data['status'])) {
            $status = ClientsForLifeStatus::tryFrom((string) $data['status'])
                ?? throw new ClientsForLifeException('status muss einer von ' . implode(', ', ClientsForLifeStatus::values()) . ' sein.');
            if ($status === ClientsForLifeStatus::ARCHIVED) {
                throw new ClientsForLifeException('Neue Instanzen starten als draft oder active.');
            }
        }

        $attrs = $this->validated($data, $teamId);
        $attrs['title'] = $title;
        $attrs['team_id'] = $teamId;
        $attrs['status'] = $status;
        $attrs['created_by_user_id'] = $creator->id;
        $attrs['owner_user_id'] = $attrs['owner_user_id'] ?? $creator->id;

        if ($templateId) {
            $template = $this->findTemplate($templateId, $teamId);
            $attrs['template_id'] = $template->id;
            if (empty($attrs['description']) && $template->description) {
                $attrs['description'] = $template->description;
            }
        }

        return DB::transaction(function () use ($attrs, $entityId) {
            $client = ClientsForLife::create($attrs);

            if ($entityId) {
                $this->link($client, $entityId);
            }

            return $client->refresh();
        });
    }

    /** @param array<string,mixed> $data nur gesetzte Schlüssel werden geändert (null leert, wo erlaubt) */
    public function update(ClientsForLife $client, array $data): ClientsForLife
    {
        if (array_key_exists('title', $data) && trim((string) $data['title']) === '') {
            throw new ClientsForLifeException('Titel darf nicht leer sein.');
        }

        $attrs = $this->validated($data, $client->team_id);
        if (array_key_exists('title', $data)) {
            $attrs['title'] = trim((string) $data['title']);
        }

        $client->fill($attrs)->save();

        return $client->refresh();
    }

    public function activate(ClientsForLife $client): ClientsForLife
    {
        if ($client->trashed()) {
            throw new ClientsForLifeException('Eine gelöschte Instanz kann nicht aktiviert werden.', 'INVALID_STATE');
        }
        if ($client->status !== ClientsForLifeStatus::ACTIVE) {
            $client->status = ClientsForLifeStatus::ACTIVE;
            $client->save();
        }

        return $client->refresh();
    }

    public function archive(ClientsForLife $client): ClientsForLife
    {
        if ($client->status !== ClientsForLifeStatus::ARCHIVED) {
            $client->status = ClientsForLifeStatus::ARCHIVED;
            $client->save();
        }

        return $client->refresh();
    }

    /** Soft-Delete (wiederherstellbar). Die Knoten-Verknüpfung bleibt bestehen, wie beim Planner-Projekt. */
    public function delete(ClientsForLife $client): void
    {
        $client->delete();
    }

    // ── Knoten-Verknüpfung (Dimension Bridge) ────────────────────

    /** Instanz an einen Kunden-Knoten hängen (zusätzlich zu bestehenden Knoten). */
    public function link(ClientsForLife $client, int $entityId): void
    {
        // Das Event LINKED kommt aus dem DimensionLink-Hook (ServiceProvider), damit auch externe Pfade
        // (organization.entity.link.POST, Terminal-App "Verknüpfungen") es auslösen.
        EntityDimensionBridge::createLink($entityId, ClientsForLife::MORPH_ALIAS, $client->id, ['team_id' => $client->team_id]);
        EntityDimensionBridge::flush();
    }

    /** Verknüpfung zu einem Knoten lösen; ohne $entityId alle Knoten (Instanz wird "unverknüpft"). */
    public function unlink(ClientsForLife $client, ?int $entityId = null): void
    {
        $nodes = $entityId ? [$entityId] : $this->nodeIds($client);
        foreach ($nodes as $id) {
            EntityDimensionBridge::deleteLink($id, ClientsForLife::MORPH_ALIAS, $client->id);
        }
        EntityDimensionBridge::flush();
    }

    /** Umhängen: ersetzt alle bisherigen Knoten durch $newEntityId (wie ProjectsCleanup im Planner). */
    public function relink(ClientsForLife $client, int $newEntityId): void
    {
        $previous = $this->nodeIds($client)[0] ?? null;

        EntityDimensionBridge::replaceLinks(ClientsForLife::MORPH_ALIAS, $client->id, $newEntityId, ['team_id' => $client->team_id]);
        EntityDimensionBridge::flush();

        ClientsForLifeLinkChanged::dispatch($client, ClientsForLifeLinkChanged::RELINKED, $newEntityId, $previous);
    }

    // ── Vorlagen ─────────────────────────────────────────────────

    /** @param int|int[] $teamIds */
    public function listTemplates(int|array $teamIds): Collection
    {
        return ClientsForLifeTemplate::query()->whereIn('team_id', (array) $teamIds)->orderBy('name')->get();
    }

    public function findTemplate(int $id, int|array|null $teamIds = null): ClientsForLifeTemplate
    {
        $query = ClientsForLifeTemplate::query();
        if ($teamIds !== null) {
            $query->whereIn('team_id', (array) $teamIds);
        }

        return $query->find($id) ?? throw new ClientsForLifeException('Vorlage nicht gefunden.', 'NOT_FOUND');
    }

    /** @param array<string,mixed> $data name (Pflicht), description */
    public function createTemplate(User $creator, Team|int $team, array $data): ClientsForLifeTemplate
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new ClientsForLifeException('Name ist erforderlich.');
        }

        return ClientsForLifeTemplate::create([
            'team_id' => $team instanceof Team ? $team->id : $team,
            'name' => $name,
            'description' => ($data['description'] ?? '') === '' ? null : ($data['description'] ?? null),
            'created_by_user_id' => $creator->id,
        ])->refresh();
    }

    /** @param array<string,mixed> $data nur gesetzte Schlüssel werden geändert */
    public function updateTemplate(ClientsForLifeTemplate $template, array $data): ClientsForLifeTemplate
    {
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new ClientsForLifeException('Name darf nicht leer sein.');
            }
            $template->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $template->description = $data['description'] === '' ? null : $data['description'];
        }
        $template->save();

        return $template->refresh();
    }

    /** Soft-Delete. Bereits erzeugte Instanzen behalten ihre Referenz (Vorlage bleibt über withTrashed lesbar). */
    public function deleteTemplate(ClientsForLifeTemplate $template): void
    {
        $template->delete();
    }

    // ── intern ───────────────────────────────────────────────────

    /** @return int[] Instanz-IDs, die am Knoten hängen */
    private function clientIdsAtNode(int $entityId): array
    {
        return EntityDimensionBridge::linksForEntities([$entityId])
            ->where('linkable_type', ClientsForLife::MORPH_ALIAS)
            ->pluck('linkable_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Validiert/normalisiert die Eingabefelder. Nur vorhandene Schlüssel kommen zurück.
     *
     * @return array<string,mixed>
     */
    private function validated(array $data, int $teamId): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data) && $field !== 'title') {
                $out[$field] = $data[$field] === '' ? null : $data[$field];
            }
        }

        if (! empty($out['owner_user_id'])) {
            $owner = User::find((int) $out['owner_user_id']);
            if (! $owner || ! $owner->teams()->where('teams.id', $teamId)->exists()) {
                throw new ClientsForLifeException('owner_user_id ist kein Mitglied des Teams.');
            }
        }

        if (! empty($out['responsible_agent_user_id'])) {
            $agent = User::find((int) $out['responsible_agent_user_id']);
            if (! $agent || $agent->type !== 'ai_user') {
                throw new ClientsForLifeException('responsible_agent_user_id muss ein Agent-User (type ai_user) sein.');
            }
        }

        return $out;
    }
}
