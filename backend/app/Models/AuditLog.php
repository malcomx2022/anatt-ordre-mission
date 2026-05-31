<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // table à created_at seul

    protected $fillable = [
        'user_id',
        'action',
        'entite',
        'entite_id',
        'donnees',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'donnees' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
