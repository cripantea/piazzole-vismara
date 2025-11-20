@extends('layout')

@section('title', 'Piazzole')

@section('content')
    <div class="bg-white rounded-lg shadow-sm p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Piazzole</h1>
            <a href="{{ route('piazzole.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                + Nuova Piazzola
            </a>
        </div>

        <!-- Messaggi di successo -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Barra di ricerca -->
        <form method="GET" action="{{ route('piazzole.index') }}" class="mb-6">
            <div class="flex gap-2">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Cerca per identificativo o nome..."
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Cerca
                </button>
                @if(request('search'))
                    <a href="{{ route('piazzole.index') }}"
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        <!-- Tabella -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('piazzole.index', ['sort_by' => 'identificativo', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}"
                           class="hover:text-gray-700">
                            Identificativo
                            @if(request('sort_by', 'identificativo') === 'identificativo')
                                <span>{{ request('sort_order', 'asc') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('piazzole.index', ['sort_by' => 'nome', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}"
                           class="hover:text-gray-700">
                            Nome
                            @if(request('sort_by') === 'nome')
                                <span>{{ request('sort_order', 'asc') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Azioni
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">

                @forelse($piazzole as $piazzola)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $piazzola->identificativo }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $piazzola->nome }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">

                            <a href="{{ route('piazzole.edit', $piazzola->id) }}"
                               class="text-blue-600 hover:text-blue-900 mr-3">
                                Modifica
                            </a>
                            <form action="{{ route('piazzole.destroy', $piazzola->id) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Sei sicuro di voler eliminare questa piazzola?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    Elimina
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                            Nessuna piazzola trovata
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginazione -->
        <div class="mt-6">
            {{ $piazzole->withQueryString()->links() }}
        </div>
    </div>
@endsection