<?php

namespace Platform\ClientsForLife\Policies;

use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\Core\Models\User;
use Platform\Core\Policies\RolePolicy;

/**
 * Vorlagen sind teamweit (nicht an Kunden-Knoten verortet): sichtbar/nutzbar für Mitglieder des
 * Team-Familienbaums (Root-Team + Kind-Teams), ändern/löschen für Ersteller oder Mitglieder des Vorlagen-Teams.
 */
class ClientsForLifeTemplatePolicy extends RolePolicy
{
    public function view(User $user, $template): bool
    {
        return $this->inTeamFamily($user, $template);
    }

    public function update(User $user, $template): bool
    {
        return $this->inTeamFamily($user, $template);
    }

    public function delete(User $user, $template): bool
    {
        return $this->inTeamFamily($user, $template)
            && ((int) $template->created_by_user_id === (int) $user->id
                || $user->teams()->where('teams.id', $template->team_id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    protected function inTeamFamily(User $user, $template): bool
    {
        if (! $template instanceof ClientsForLifeTemplate || ! $user->currentTeam) {
            return false;
        }

        return in_array((int) $template->team_id, self::familyTeamIds($user), true);
    }

    /** @return int[] Root-Team + alle Kind-Teams des aktuellen Teams des Users */
    public static function familyTeamIds(User $user): array
    {
        $current = $user->currentTeam;
        if (! $current) {
            return [];
        }

        $root = $current->getRootTeam();
        $ids = [(int) $root->id];
        $walker = function ($team) use (&$walker, &$ids) {
            foreach ($team->childTeams()->get() as $child) {
                $ids[] = (int) $child->id;
                $walker($child);
            }
        };
        $walker($root);

        return array_values(array_unique($ids));
    }

    protected function getUserRole(User $user, $model): ?string
    {
        return null;
    }
}
