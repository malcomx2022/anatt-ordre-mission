<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Paiement extends Model
{
    use HasFactory;

    protected $fillable = [
        'ordre_mission_id',
        'agent_id',
        'operateur',
        'numero_destinataire',
        'montant',
        'statut',
        'reference_transaction',
        'fichier_virement',
        'date_virement',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_virement' => 'datetime',
        ];
    }

    public function ordreMission()
    {
        return $this->belongsTo(OrdreMission::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
