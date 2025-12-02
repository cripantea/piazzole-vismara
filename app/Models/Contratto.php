<?php
// app/Models/Contratto.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Contratto extends Model
{
    protected $table = 'contratti';

    protected $fillable = [
        'piazzola_id',
        'cliente_id',
        'data_inizio',
        'data_fine',
        'valore',
        'numero_rate',
        'stato',
        'rinnovo_automatico',
        'rinnovo_automatico_at'
    ];

    protected $casts = [
        'data_inizio' => 'date',
        'data_fine' => 'date',
        'valore' => 'decimal:2',
        'rinnovo_automatico' => 'boolean',
        'rinnovo_automatico_at' => 'datetime'
    ];

// Scope per contratti da confermare
    public function scopeDaConfermare($query)
    {
        return $query->where('rinnovo_automatico', true)
            ->where('stato', 'attivo');
    }

// Verifica se è un rinnovo da confermare
    public function isRinnovoAutomatico()
    {
        return $this->rinnovo_automatico === true && $this->stato === 'attivo';
    }
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

    // Verifica se ha scadenze pagate
    public function hasScadenzePagate()
    {
        return $this->scadenze()->whereNotNull('data_pagamento')->exists();
    }

    // Verifica se tutte le scadenze sono pagate
    public function tutteScadenzePagate()
    {
        return $this->scadenze()->count() > 0 &&
            $this->scadenze()->whereNull('data_pagamento')->count() === 0;
    }

    // Calcola la durata in mesi
    public function durataMesi()
    {
        return $this->data_inizio->diffInMonths($this->data_fine) + 1;
    }
}