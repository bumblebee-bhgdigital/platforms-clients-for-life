<?php

namespace Platform\ClientsForLife\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Vorlage (Template) für Clients-for-Life-Instanzen, z. B. "Standard" oder "Key Account". Grundgerüst: Name + Beschreibung; Struktur-Inhalte (Beziehungsnetz, Touchpoint-Raster, Gesundheitscheck) folgen in eigenen Boards. Vorlagen sind teamweit, nicht an Kunden-Knoten verortet.
 *
 * @property-read string $uuid
 */
class ClientsForLifeTemplate extends Model implements HasDisplayName
{
    use SoftDeletes;

    protected $table = 'clients_for_life_templates';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'created_by_user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
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
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(ClientsForLife::class, 'template_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }
}
