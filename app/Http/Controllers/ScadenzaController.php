<?php

// app/Http/Controllers/ScadenzaController.php
namespace App\Http\Controllers;

use App\Models\Scadenza;
use App\Models\Cliente;
use App\Models\Piazzola;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScadenzaController extends Controller
{
    public function index(Request $request)
    {
        $query = Scadenza::with(['contratto.cliente', 'contratto.piazzola']);

        // Filtro per Cliente
        if ($request->filled('cliente_id')) {
            $query->whereHas('contratto', function($q) use ($request) {
                $q->where('cliente_id', $request->cliente_id);
            });
        }

        // Filtro per Piazzola
        if ($request->filled('piazzola_id')) {
            $query->whereHas('contratto', function($q) use ($request) {
                $q->where('piazzola_id', $request->piazzola_id);
            });
        }

        // Filtro per Data specifica
        if ($request->filled('data')) {
            $query->whereDate('data', $request->data);
        }

        // Filtro per Mese/Anno
        if ($request->filled('mese') && $request->filled('anno')) {
            $query->whereYear('data', $request->anno)
                ->whereMonth('data', $request->mese);
        }

        // Filtro Pagato/Non Pagato
        if ($request->filled('stato_pagamento')) {
            if ($request->stato_pagamento === 'pagato') {
                $query->whereNotNull('data_pagamento');
            } elseif ($request->stato_pagamento === 'non_pagato') {
                $query->whereNull('data_pagamento');
            }
        }

        // Filtro Scadute (non pagate e data passata di almeno 1 giorno)
        if ($request->filled('scadute') && $request->scadute == '1') {
            $query->whereNull('data_pagamento')
                ->where('data', '<', now()->subDay());
        }

        // Ordinamento
        $sortBy = $request->get('sort_by', 'data');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $scadenze = $query->paginate(15);

        // Dati per i dropdown dei filtri
        $clienti = Cliente::orderBy('nome')->get();
        $piazzole = Piazzola::orderBy('identificativo')->get();

        return view('scadenze.index', compact('scadenze', 'clienti', 'piazzole'));
    }

    public function update(Request $request, Scadenza $scadenza)
    {
        $validated = $request->validate([
            'data_pagamento' => 'nullable|date'
        ]);

        $scadenza->update($validated);

        return redirect()->route('scadenze.index')
            ->with('success', 'Scadenza aggiornata con successo!');
    }

    // Metodo per segnare come pagata
    public function segnaComePagata(Scadenza $scadenza)
    {
        $scadenza->update([
            'data_pagamento' => now()->format('Y-m-d')
        ]);

        return redirect()->back()
            ->with('success', 'Scadenza segnata come pagata!');
    }

    // Metodo per rimuovere il pagamento
    public function rimuoviPagamento(Scadenza $scadenza)
    {
        $scadenza->update([
            'data_pagamento' => null
        ]);

        return redirect()->back()
            ->with('success', 'Pagamento rimosso!');
    }
}




