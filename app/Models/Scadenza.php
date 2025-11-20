<?php
// app/Models/Scadenza.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Scadenza extends Model
{
    protected $table = 'scadenze';

    protected $fillable = [
        'contratto_id',
        'numero_rata',
        'data',
        'importo',
        'data_pagamento'
    ];

    protected $casts = [
        'data' => 'date',
        'data_pagamento' => 'date',
        'importo' => 'decimal:2'
    ];

    public function contratto()
    {
        return $this->belongsTo(Contratto::class);
    }

    // Verifica se è pagata
    public function isPagata()
    {
        return !is_null($this->data_pagamento);
    }

    // Verifica se è scaduta (non pagata e data nel passato)
    public function isScaduta()
    {
        return is_null($this->data_pagamento) && $this->data->isPast();
    }

    // Scope per scadenze non pagate
    public function scopeNonPagate($query)
    {
        return $query->whereNull('data_pagamento');
    }

    // Scope per scadenze pagate
    public function scopePagate($query)
    {
        return $query->whereNotNull('data_pagamento');
    }

    // Scope per scadenze scadute
    public function scopeScadute($query)
    {
        return $query->whereNull('data_pagamento')
            ->where('data', '<', now());
    }
}
