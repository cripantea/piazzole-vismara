<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contratto extends Model
{
    /** @use HasFactory<\Database\Factories\ContrattoFactory> */
    use HasFactory;

    protected $table = 'contratti';
    protected $guarded = [];


    protected $casts = [
        'data_inizio' => 'date',
        'data_fine' => 'date',
        'valore' => 'decimal:2'
    ];

    public function piazzola()
    {
        return $this->belongsTo(Piazzola::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function scadenze()
    {
        return $this->hasMany(Scadenza::class);
    }

    public function prossimaScadenza()
    {
        return $this->hasOne(Scadenza::class)
            ->whereNull('data_pagamento')
            ->orderBy('data', 'asc');
    }

    public function scadenzeNonPagate()
    {
        return $this->hasMany(Scadenza::class)
            ->whereNull('data_pagamento')
            ->orderBy('data', 'asc');
    }

    public function scadenzePagate()
    {
        return $this->hasMany(Scadenza::class)
            ->whereNotNull('data_pagamento')
            ->orderBy('data', 'asc');
    }

    // Calcola la durata in mesi
    public function durataMesi()
    {
        return $this->data_inizio->diffInMonths($this->data_fine) + 1;
    }
}
