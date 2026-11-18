<?php

namespace Platform\ClientsForLife\Enums;

/**
 * Status einer Clients-for-Life-Instanz.
 *
 * Übergänge: draft -> active; jeder Status -> archived; archived -> active (Reaktivieren).
 * Anders als beim Management Book gibt es keine "Eine aktive Instanz pro Knoten"-Regel
 * (wie beim Planner-Projekt: beliebig viele Instanzen pro Kunden-Knoten).
 */
enum ClientsForLifeStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf',
            self::ACTIVE => 'Aktiv',
            self::ARCHIVED => 'Archiviert',
        };
    }

    /** nx-badge variant */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::ACTIVE => 'success',
            self::ARCHIVED => 'neutral',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
