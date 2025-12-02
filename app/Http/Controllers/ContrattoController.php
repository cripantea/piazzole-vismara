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

        // Ordinamento - i contratti da confermare vanno SEMPRE in cima
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Prima ordina per rinnovo_automatico DESC (true prima di false)
        // poi applica l'ordinamento richiesto
        $query->orderBy('rinnovo_automatico', 'desc')
            ->orderBy($sortBy, $sortOrder);

        $contratti = $query->paginate(15);

        return view('contratti.index', compact('contratti'));
    }

// Conferma rinnovo automatico
    public function confermaRinnovo(Contratto $contratto)
    {
        if (!$contratto->isRinnovoAutomatico()) {
            return redirect()->back()
                ->with('error', 'Questo contratto non è un rinnovo automatico da confermare.');
        }

        $contratto->update([
            'rinnovo_automatico' => false,
            'rinnovo_automatico_at' => null
        ]);

        return redirect()->route('contratti.index')
            ->with('success', 'Rinnovo confermato con successo!');
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

    public function update(Request $request, Contratto $contratto)
    {
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
            // Se era un rinnovo automatico, confermalo
            $wasRinnovoAutomatico = $contratto->isRinnovoAutomatico();

            // Aggiorna il contratto
            $contratto->update([
                'piazzola_id' => $validated['piazzola_id'],
                'cliente_id' => $validated['cliente_id'],
                'data_inizio' => $validated['data_inizio'],
                'data_fine' => $validated['data_fine'],
                'valore' => $validated['valore'],
                'numero_rate' => $validated['numero_rate'],
                'stato' => $validated['stato'],
                'rinnovo_automatico' => false, // Conferma automaticamente
                'rinnovo_automatico_at' => null
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
            ->with('success', 'Contratto aggiornato' . ($contratto->wasChanged('rinnovo_automatico') ? ' e confermato' : '') . ' con successo!');
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

    public function showChiusura(int $id)
    {
        $contratto = Contratto::findOrFail($id);
        return view('contratti.chiusura', compact('contratto'));
    }

// Chiudi contratto con data specifica
    public function chiudi(Request $request, int $id)
    {
        $contratto = Contratto::findOrFail($id);
        //dd($contratto);
        $validated = $request->validate([
            'data_chiusura' => 'required|date|after_or_equal:' . $contratto->data_inizio->format('Y-m-d')
        ]);

        $dataChiusura = Carbon::parse($validated['data_chiusura']);

        DB::transaction(function () use ($contratto, $dataChiusura) {
            // Aggiorna la data fine del contratto
            $contratto->update([
                'data_fine' => $dataChiusura,
                'stato' => 'completato'
            ]);

            // Elimina le scadenze non pagate successive alla data di chiusura
            $scadenzeEliminare = $contratto->scadenze()
                ->whereNull('data_pagamento')
                ->where('data', '>', $dataChiusura)
                ->get();

            foreach ($scadenzeEliminare as $scadenza) {
                $scadenza->delete();
            }

            // Ricalcola il valore del contratto basandosi sulle scadenze rimaste
            $nuovoValore = $contratto->scadenze()->sum('importo');
            $contratto->update([
                'valore' => $nuovoValore,
                'numero_rate' => $contratto->scadenze()->count()
            ]);
        });

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto chiuso con successo! Scadenze successive eliminate.');
    }

// Rinnova contratto
    public function rinnova(int $id)
    {
        $contratto = Contratto::findOrFail($id);
        if (!$contratto->tutteScadenzePagate()) {
            return redirect()->back()
                ->with('error', 'Impossibile rinnovare il contratto: ci sono ancora scadenze non pagate!');
        }

        DB::transaction(function () use ($contratto) {
            // Il contratto corrente è già completato o lo chiudiamo
            if ($contratto->stato !== 'completato') {
                $contratto->update(['stato' => 'completato']);
            }

            // Calcola le nuove date
            $durataMesi = $contratto->data_inizio->diffInMonths($contratto->data_fine);
            $nuovaDataInizio = $contratto->data_fine->copy()->addDay();
            $nuovaDataFine = $nuovaDataInizio->copy()->addMonths($durataMesi);

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


}
