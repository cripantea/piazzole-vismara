<?php

namespace App\Http\Controllers;

use App\Models\Piazzola;
use Illuminate\Http\Request;

class PiazzolaController extends Controller
{
    public function index(Request $request)
    {
        $query = Piazzola::query();

        // Filtro per ricerca
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('identificativo', 'like', "%$search%")
                ->orWhere('nome', 'like', "%{$search}%");
        }

        // Ordinamento
        $sortBy = $request->get('sort_by', 'identificativo');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $piazzole = $query->paginate(15);

        return view('piazzole.index', compact('piazzole'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'identificativo' => 'required|string|max:255|unique:piazzole',
            'nome' => 'required|string|max:255'
        ]);

        Piazzola::create($validated);

        return redirect()->route('piazzole.index')
            ->with('success', 'Piazzola creata con successo!');
    }

    public function create()
    {
        return view('piazzole.create');
    }

    public function edit(int $id)
    {
        $piazzola = Piazzola::findOrFail($id);
        //dd($id);
        return view('piazzole.edit', compact('piazzola'));
    }

    public function update(Request $request, Piazzola $piazzola)
    {
        $validated = $request->validate([
            'identificativo' => 'required|string|max:255|unique:piazzole,identificativo,' . $piazzola->id,
            'nome' => 'required|string|max:255'
        ]);

        $piazzola->update($validated);

        return redirect()->route('piazzole.index')
            ->with('success', 'Piazzola aggiornata con successo!');
    }

    public function destroy(int $id)
    {
        $piazzola = Piazzola::findOrFail($id);
        $piazzola->delete();

        return redirect()->route('piazzole.index')
            ->with('success', 'Piazzola eliminata con successo!');
    }
}
