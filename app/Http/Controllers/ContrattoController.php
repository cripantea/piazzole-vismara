<?php
// app/Http/Controllers/ContrattoController.php
namespace App\Http\Controllers;

use App\Models\Contratto;
use App\Models\Piazzola;
use App\Models\Cliente;
use App\Models\Scadenza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContrattoController extends Controller
{
    public function index(Request $request)
    {
        $query = Contratto::with(['piazzola', 'cliente', 'prossimaScadenza']);

        // Filtro per ricerca
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('piazzola', function($q) use ($search) {
                $q->where('identificativo', 'like', "%{$search}%");
            })->orWhereHas('cliente', function($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%");
            });
        }

        // Ordinamento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $contratti = $query->paginate(15);

        return view('contratti.index', compact('contratti'));
    }

    public function create()
    {
        $piazzole = Piazzola::orderBy('identificativo')->get();
        $clienti = Cliente::orderBy('nome')->get();

        return view('contratti.create', compact('piazzole', 'clienti'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'piazzola_id' => 'required|exists:piazzole,id',
            'cliente_id' => 'required|exists:clienti,id',
            'data_inizio' => 'required|date',
            'data_fine' => 'required|date|after:data_inizio',
            'valore' => 'required|numeric|min:0',
            'numero_rate' => 'required|integer|min:1',
            'scadenze' => 'required|array',
            'scadenze.*.data' => 'required|date',
            'scadenze.*.importo' => 'required|numeric|min:0',
        ]);

        // VALIDAZIONE: Verifica che la somma delle scadenze corrisponda al valore del contratto
        $sommaScadenze = collect($validated['scadenze'])->sum('importo');
        $valoreContratto = (float) $validated['valore'];

        // Tolleranza di 0.01€ per arrotondamenti
        if (abs($sommaScadenze - $valoreContratto) > 0.01) {
            return back()
                ->withInput()
                ->withErrors([
                    'scadenze' => sprintf(
                        'La somma delle scadenze (€ %s) non corrisponde al valore del contratto (€ %s). Differenza: € %s',
                        number_format($sommaScadenze, 2, ',', '.'),
                        number_format($valoreContratto, 2, ',', '.'),
                        number_format(abs($sommaScadenze - $valoreContratto), 2, ',', '.')
                    )
                ]);
        }

        DB::transaction(function () use ($validated) {
            // Crea il contratto
            $contratto = Contratto::create([
                'piazzola_id' => $validated['piazzola_id'],
                'cliente_id' => $validated['cliente_id'],
                'data_inizio' => $validated['data_inizio'],
                'data_fine' => $validated['data_fine'],
                'valore' => $validated['valore'],
                'numero_rate' => $validated['numero_rate'],
            ]);

            // Crea le scadenze
            foreach ($validated['scadenze'] as $index => $scadenzaData) {
                Scadenza::create([
                    'contratto_id' => $contratto->id,
                    'numero_rata' => $index + 1,
                    'data' => $scadenzaData['data'],
                    'importo' => $scadenzaData['importo'],
                ]);
            }
        });

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto creato con successo!');
    }
    public function edit(int $id)
    {
        $contratto=Contratto::findOrFail($id);
        $contratto->load('scadenze');
        $piazzole = Piazzola::orderBy('identificativo')->get();
        $clienti = Cliente::orderBy('nome')->get();

        return view('contratti.edit', compact('contratto', 'piazzole', 'clienti'));
    }

    public function update(Request $request, int $id)
    {
        $contratto=Contratto::findOrFail($id);
        $validated = $request->validate([
            'piazzola_id' => 'required|exists:piazzole,id',
            'cliente_id' => 'required|exists:clienti,id',
            'data_inizio' => 'required|date',
            'data_fine' => 'required|date|after:data_inizio',
            'valore' => 'required|numeric|min:0',
            'numero_rate' => 'required|integer|min:1',
            'stato' => 'required|in:attivo,completato,sospeso',
            'scadenze' => 'required|array',
            'scadenze.*.id' => 'required|exists:scadenze,id',
            'scadenze.*.data' => 'required|date',
            'scadenze.*.importo' => 'required|numeric|min:0',
        ]);

        // Validazione: somma scadenze = valore contratto
        $sommaScadenze = collect($validated['scadenze'])->sum('importo');
        $valoreContratto = (float) $validated['valore'];

        if (abs($sommaScadenze - $valoreContratto) > 0.01) {
            return back()
                ->withInput()
                ->withErrors([
                    'scadenze' => sprintf(
                        'La somma delle scadenze (€ %s) non corrisponde al valore del contratto (€ %s). Differenza: € %s',
                        number_format($sommaScadenze, 2, ',', '.'),
                        number_format($valoreContratto, 2, ',', '.'),
                        number_format(abs($sommaScadenze - $valoreContratto), 2, ',', '.')
                    )
                ]);
        }

        DB::transaction(function () use ($validated, $contratto) {
            // Aggiorna il contratto
            $contratto->update([
                'piazzola_id' => $validated['piazzola_id'],
                'cliente_id' => $validated['cliente_id'],
                'data_inizio' => $validated['data_inizio'],
                'data_fine' => $validated['data_fine'],
                'valore' => $validated['valore'],
                'numero_rate' => $validated['numero_rate'],
                'stato' => $validated['stato'],
            ]);

            // Aggiorna le scadenze non pagate
            foreach ($validated['scadenze'] as $scadenzaData) {
                $scadenza = Scadenza::find($scadenzaData['id']);

                // Aggiorna solo se non è stata pagata
                if (is_null($scadenza->data_pagamento)) {
                    $scadenza->update([
                        'data' => $scadenzaData['data'],
                        'importo' => $scadenzaData['importo'],
                    ]);
                }
            }
        });

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto aggiornato con successo!');
    }

    public function destroy(int $id)
    {
        // Impedisci eliminazione se ci sono scadenze pagate
        $contratto=Contratto::findOrFail($id);
        if ($contratto->hasScadenzePagate()) {
            return redirect()->route('contratti.index')
                ->with('error', 'Impossibile eliminare il contratto: ci sono scadenze già pagate!');
        }

        $contratto->delete();

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto eliminato con successo!');
    }

// Chiudi contratto (segna come completato)
    public function chiudi(int $id)
    {
        $contratto=Contratto::findOrFail($id);
        if (!$contratto->tutteScadenzePagate()) {
            return redirect()->back()
                ->with('error', 'Impossibile chiudere il contratto: ci sono ancora scadenze non pagate!');
        }

        $contratto->update(['stato' => 'completato']);

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto chiuso con successo!');
    }

// Rinnova contratto (crea un nuovo contratto per il periodo successivo)
    public function rinnova(int $id)
    {
        $contratto=Contratto::findOrFail($id);
        if (!$contratto->tutteScadenzePagate()) {
            return redirect()->back()
                ->with('error', 'Impossibile rinnovare il contratto: ci sono ancora scadenze non pagate!');
        }

        DB::transaction(function () use ($contratto) {
            // Chiudi il contratto corrente
            $contratto->update(['stato' => 'completato']);

            // Calcola le nuove date
            $durataMesi = $contratto->durataMesi();
            $nuovaDataInizio = $contratto->data_fine->copy()->addDay();
            $nuovaDataFine = $nuovaDataInizio->copy()->addMonths($durataMesi)->subDay();

            // Crea nuovo contratto
            $nuovoContratto = Contratto::create([
                'piazzola_id' => $contratto->piazzola_id,
                'cliente_id' => $contratto->cliente_id,
                'data_inizio' => $nuovaDataInizio,
                'data_fine' => $nuovaDataFine,
                'valore' => $contratto->valore,
                'numero_rate' => $contratto->numero_rate,
                'stato' => 'attivo',
            ]);

            // Crea le nuove scadenze basate su quelle vecchie
            $intervalloMesi = $durataMesi / $contratto->numero_rate;

            foreach ($contratto->scadenze as $index => $vecchiaScadenza) {
                $mesiDaAggiungere = round($intervalloMesi * $index);
                $nuovaData = $nuovaDataInizio->copy()->addMonths($mesiDaAggiungere);

                Scadenza::create([
                    'contratto_id' => $nuovoContratto->id,
                    'numero_rata' => $vecchiaScadenza->numero_rata,
                    'data' => $nuovaData,
                    'importo' => $vecchiaScadenza->importo,
                ]);
            }
        });

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto rinnovato con successo per il periodo successivo!');
    }

    // API endpoint per generare le scadenze in anteprima
    public function generaScadenze(Request $request)
    {
        $validated = $request->validate([
            'data_inizio' => 'required|date',
            'data_fine' => 'required|date|after:data_inizio',
            'valore' => 'required|numeric|min:0',
            'numero_rate' => 'required|integer|min:1',
        ]);

        $dataInizio = Carbon::parse($validated['data_inizio']);
        $dataFine = Carbon::parse($validated['data_fine']);
        $numeroRate = $validated['numero_rate'];
        $valore = $validated['valore'];

        // Calcola la durata totale in mesi
        $durataMesi = $dataInizio->diffInMonths($dataFine);

        // Calcola l'intervallo tra le rate in mesi
        $intervalloMesi = $durataMesi / $numeroRate;

        // Calcola l'importo per rata
        $importoRata = $valore / $numeroRate;

        $scadenze = [];

        for ($i = 0; $i < $numeroRate; $i++) {
            // Calcola i mesi da aggiungere e arrotonda
            $mesiDaAggiungere = round($intervalloMesi * $i);

            // Mantieni lo stesso giorno della data di inizio
            $dataScadenza = $dataInizio->copy()->addMonths($mesiDaAggiungere);

            $scadenze[] = [
                'numero_rata' => $i + 1,
                'data' => $dataScadenza->format('Y-m-d'), // era data_scadenza
                'importo' => round($importoRata, 2),
            ];

        }

        return response()->json(['scadenze' => $scadenze]);
    }
}
