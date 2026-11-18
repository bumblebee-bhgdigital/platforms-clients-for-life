<?php

namespace Platform\ClientsForLife\Policies;

use Platform\Core\Authz\AuthzResolver;
use Platform\Core\Models\User;
use Platform\Core\Policies\RolePolicy;
use Platform\ClientsForLife\Models\ClientsForLife;

/**
 * Kein eigenes Rollensystem — gleiche Graph-Autorisierung wie PlannerProjectPolicy:
 * Ersteller (owns) ODER strukturell im Org-Graphen erreichbar (may). read < write < manage.
 * Zusätzlich darf der im Instanz eingetragene Owner (owner_user_id) lesen/ändern.
 * Teilen = die Instanz im Graphen (an einem Knoten) aufhängen.
 */
class ClientsForLifePolicy extends RolePolicy
{
    public function view(User $user, $client): bool
    {
        return $this->graphAllows($user, $client, 'read');
    }

    public function update(User $user, $client): bool
    {
        return $this->graphAllows($user, $client, 'write');
    }

    public function delete(User $user, $client): bool
    {
        return $this->graphAllows($user, $client, 'manage');
    }

    public function forceDelete(User $user, $client): bool
    {
        return $this->graphAllows($user, $client, 'manage');
    }

    /** Aktivieren/Ablösen/Archivieren/Umhängen = Schreibrecht. */
    public function activate(User $user, $client): bool
    {
        return $this->update($user, $client);
    }

    public function create(User $user): bool
    {
        // Jedes Team-Mitglied kann Instanzen anlegen (wird sein Ersteller).
        return $user->currentTeam !== null;
    }

    protected function graphAllows(User $user, $client, string $cap): bool
    {
        if (! $client || ! $client->id) {
            return false;
        }

        if ($client->owner_user_id && (int) $client->owner_user_id === (int) $user->id) {
            return true;
        }

        $resolver = app(AuthzResolver::class);
        $type = ClientsForLife::class;

        return $resolver->may($user, $cap, $type, (int) $client->id)
            || $resolver->owns($user, $type, (int) $client->id);
    }

    protected function getUserRole(User $user, $model): ?string
    {
        return null;
    }
}
