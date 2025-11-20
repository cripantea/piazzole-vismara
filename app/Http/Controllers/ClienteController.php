<?php

// app/Http/Controllers/ClienteController.php
namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::query();

        // Filtro per ricerca
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nome', 'like', "%{$search}%");
        }

        // Ordinamento
        $sortBy = $request->get('sort_by', 'nome');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $clienti = $query->paginate(15);

        return view('clienti.index', compact('clienti'));
    }

    public function create()
    {
        return view('clienti.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255'
        ]);

        Cliente::create($validated);

        return redirect()->route('clienti.index')
            ->with('success', 'Cliente creato con successo!');
    }

    public function edit(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        return view('clienti.edit', compact('cliente'));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255'
        ]);
        $cliente = Cliente::findOrFail($id);
        $cliente->update($validated);

        return redirect()->route('clienti.index')
            ->with('success', 'Cliente aggiornato con successo!');
    }

    public function destroy(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->delete();

        return redirect()->route('clienti.index')
            ->with('success', 'Cliente eliminato con successo!');
    }
}