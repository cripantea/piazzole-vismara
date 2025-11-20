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

        try {
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
        } catch (\Exception $ex) {
            throw $ex;
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

            foreach ($validated['scadenze'] as $index => $scadenzaData) {
                Scadenza::create([
                    'contratto_id' => $contratto->id,
                    'numero_rata' => $index + 1,
                    'data' => $scadenzaData['data'], // era data_scadenza
                    'importo' => $scadenzaData['importo'],
                ]);
            }
        });

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto creato con successo!');
    }

    public function edit(int $id)
    {
        $contratto = Contratto::findOrFail($id);
        $contratto->load('scadenze');
        $piazzole = Piazzola::orderBy('identificativo')->get();
        $clienti = Cliente::orderBy('nome')->get();

        return view('contratti.edit', compact('contratto', 'piazzole', 'clienti'));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'piazzola_id' => 'required|exists:piazzole,id',
            'cliente_id' => 'required|exists:clienti,id',
            'data_inizio' => 'required|date',
            'data_fine' => 'required|date|after:data_inizio',
            'valore' => 'required|numeric|min:0',
            'stato' => 'required|in:attivo,completato,sospeso',
        ]);
        $contratto= Contratto::findOrFail($id);
        $contratto->update($validated);

        return redirect()->route('contratti.index')
            ->with('success', 'Contratto aggiornato con successo!');
    }

    public function destroy(int $id)
    {
        $contratto= Contratto::findOrFail($id);
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
}
