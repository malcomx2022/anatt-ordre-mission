<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification in-app d'un utilisateur (feed de la cloche).
 *
 * Distincte des notifications e-mail (job SendNotification) : ici on persiste
 * l'événement pour l'afficher dans l'application. `lu_le` NULL = non lue.
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'titre', 'message', 'data', 'lu_le'];

    protected $casts = [
        'data' => 'array',
        'lu_le' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<Notification>  $query */
    public function scopeNonLues(Builder $query): void
    {
        $query->whereNull('lu_le');
    }

    /** @param  Builder<Notification>  $query */
    public function scopePour(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
