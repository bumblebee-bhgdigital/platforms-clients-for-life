<?php

namespace Platform\ClientsForLife\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Platform\ClientsForLife\Models\ClientsForLife;

/**
 * Wird dispatched, wenn die Knoten-Verknüpfung (DimensionLink) einer Instanz sich ändert
 * (verknüpft / gelöst / umgehängt).
 *
 * Dient der Sidebar-Verknüpfung: Livewire-Komponenten hören auf das UI-Event `updateSidebar`
 * (siehe Sidebar::updateSidebar) — dieses Laravel-Event ist die serverseitige Entsprechung
 * für Listener anderer Module (z. B. Cache-Invalidierung, Signale).
 */
class ClientsForLifeLinkChanged
{
    use Dispatchable;

    public const LINKED = 'linked';
    public const UNLINKED = 'unlinked';
    public const RELINKED = 'relinked';

    public function __construct(
        public readonly ClientsForLife $client,
        public readonly string $action,
        public readonly ?int $entityId = null,
        public readonly ?int $previousEntityId = null,
    ) {
    }
}
