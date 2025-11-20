@extends('layout')

@section('title', 'Modifica Cliente')

@section('content')
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-sm p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Modifica Cliente</h1>

        <form action="{{ route('clienti.update', $cliente->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">
                    Nome *
                </label>
                <input type="text"
                       name="nome"
                       id="nome"
                       value="{{ old('nome', $cliente->nome) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('nome') border-red-500 @enderror"
                       required
                       autofocus>
                @error('nome')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Aggiorna
                </button>
                <a href="{{ route('clienti.index') }}"
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition-colors">
                    Annulla
                </a>
            </div>
        </form>
    </div>
@endsection