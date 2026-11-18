<?php

namespace Platform\ClientsForLife\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ClientsForLife\Enums\ClientsForLifeStatus;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Models\Concerns\HasEntityLinks;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Clients for Life = Instanz pro Kunde: hält die gelebte Beziehung zu einem Kunden (Beziehungsnetz, Touchpoints, Gesundheitscheck, Verantwortlicher) und entsteht aus einer Vorlage (ClientsForLifeTemplate). Hängt lose per DimensionLink an einem Kunden-Knoten (Organisations-Entity).
 *
 * Morph-Alias: `clients_for_life` (Relation::morphMap im ServiceProvider). Der Knoten hängt NICHT als
 * Fremdschlüssel an der Instanz, sondern per DimensionLink (Entity-Dimension der Organization) —
 * wie PlannerProject. Verknüpfung/Lösen/Umhängen laufen über ClientsForLifeService.
 *
 * @property-read string $uuid
 */
class ClientsForLife extends Model implements HasDisplayName
{
    use SoftDeletes, HasEntityLinks;

    /** Morph-Alias für DimensionLinks / EntityLinkProvider. */
    public const MORPH_ALIAS = 'clients_for_life';

    protected $table = 'clients_for_life';

    protected $fillable = [
        'uuid',
        'team_id',
        'title',
        'description',
        'status',
        'template_id',
        'owner_user_id',
        'responsible_agent_user_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'status' => ClientsForLifeStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::withTrashed()->where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
            if (empty($model->status)) {
                $model->status = ClientsForLifeStatus::ACTIVE;
            }
        });
    }

    // ── Beziehungen ──────────────────────────────────────────────

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ClientsForLifeTemplate::class, 'template_id')->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_user_id');
    }

    public function responsibleAgent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'responsible_agent_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    /**
     * Relationspfade der Kinder (Zeit-Kaskade, vgl. HasChildContextRelations im Planner).
     * Noch keine Kinder – Touchpoints/Gesundheitschecks ergänzen ihre Relationen hier.
     *
     * @return string[]
     */
    public static function childContextRelations(): array
    {
        return [];
    }

    // ── Anzeige ──────────────────────────────────────────────────

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    // ── Scopes / Sichtbarkeit ────────────────────────────────────

    public function scopeStatus(Builder $query, ClientsForLifeStatus|string $status): Builder
    {
        return $query->where($query->getModel()->getTable() . '.status', $status instanceof ClientsForLifeStatus ? $status->value : $status);
    }

    /**
     * Sichtbar = Core-Graph (Ersteller ODER über den Org-Baum erreichbar, read) ODER Owner der Instanz.
     * Spiegelt ClientsForLifePolicy::view().
     */
    public function scopeVisibleTo(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($user, $table) {
            $q->authzVisibleTo($user, 'read')
                ->orWhere($table . '.owner_user_id', $user->id);
        });
    }

    /** Alias wie im Planner (Sidebar). */
    public function scopeViewableBy(Builder $query, \Platform\Core\Models\User $user): Builder
    {
        return $this->scopeVisibleTo($query, $user);
    }

    public function isActive(): bool
    {
        return $this->status === ClientsForLifeStatus::ACTIVE;
    }
}
