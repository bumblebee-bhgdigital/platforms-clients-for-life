<?php

namespace Platform\ClientsForLife\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\ClientsForLife\Enums\ClientsForLifeStatus;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\Organization\Contracts\EntityLinkProvider;

/**
 * Macht die Clients-for-Life-Instanz im Organisations-Modul an Kunden-Knoten (Entities) verknüpfbar —
 * DimensionLink/Bridge analog PlannerEntityLinkProvider (Alias 'project' -> hier 'clients_for_life').
 *
 * Registrierung: ClientsForLifeServiceProvider::boot() (EntityLinkRegistry::register).
 * Damit ist der Typ für organization.entity.link.POST (object_type "clients_for_life"),
 * entity.detail/summary und die Terminal-App "Verknüpfungen" adressierbar.
 *
 * Bewusst NICHT implementiert (noch keine Kennzahlen): HasMetricDefinitions, HasPersonMetrics,
 * HasContextParent (kommt mit Touchpoints/Gesundheitscheck — sie erben die Verortung von der Instanz).
 */
class ClientsForLifeEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return [ClientsForLife::MORPH_ALIAS];
    }

    public function linkTypeConfig(): array
    {
        return [
            ClientsForLife::MORPH_ALIAS => [
                'label' => 'Clients for Life',
                'singular' => 'Clients-for-Life-Instanz',
                'icon' => 'heart',
                'route' => 'clients-for-life.clients.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        if ($morphAlias === ClientsForLife::MORPH_ALIAS) {
            $query->with(['owner:id,name', 'responsibleAgent:id,name', 'template:id,name']);
        }
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        if ($morphAlias !== ClientsForLife::MORPH_ALIAS) {
            return [];
        }

        return [
            'status' => $model->status?->label(),
            'is_active' => $model->status === ClientsForLifeStatus::ACTIVE,
            'responsible' => $model->owner?->name,
            'agent' => $model->responsibleAgent?->name,
            'template' => $model->template?->name,
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            ClientsForLife::MORPH_ALIAS => [
                ['field' => 'status', 'format' => 'badge'],
                ['field' => 'responsible', 'format' => 'text'],
                ['field' => 'template', 'format' => 'prefixed_text', 'prefix' => 'Vorlage '],
            ],
        ];
    }

    /** Keine Zeiterfassung an der Instanz. */
    public function timeTrackableCascades(): array
    {
        return [];
    }

    /** Noch keine Kennzahlen (folgt mit dem Gesundheitscheck). */
    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        return [];
    }

    /** Noch keine Kind-Objekte für den Activity-Feed (Touchpoints folgen). */
    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }
}
