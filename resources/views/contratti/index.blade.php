@extends('layout')

@section('title', 'Contratti')

@section('content')
    <div class="bg-white rounded-lg shadow-sm p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Contratti</h1>
            <a href="{{ route('contratti.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                + Nuovo Contratto
            </a>
        </div>

        <!-- Messaggi di successo -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Barra di ricerca -->
        <form method="GET" action="{{ route('contratti.index') }}" class="mb-6">
            <div class="flex gap-2">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Cerca per piazzola o cliente..."
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Cerca
                </button>
                @if(request('search'))
                    <a href="{{ route('contratti.index') }}"
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
                        Piazzola
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Cliente
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Valore
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Prossima Scadenza
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Stato
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Azioni
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                @forelse($contratti as $contratto)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $contratto->piazzola->identificativo }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $contratto->cliente->nome }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            € {{ number_format($contratto->valore, 2, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            @if($contratto->prossimaScadenza)
                                {{ $contratto->prossimaScadenza->data->format('d/m/Y') }}
                                <span class="text-xs text-gray-500">
                                    (€ {{ number_format($contratto->prossimaScadenza->importo, 2, ',', '.') }})
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $contratto->stato === 'attivo' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $contratto->stato === 'completato' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $contratto->stato === 'sospeso' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                {{ ucfirst($contratto->stato) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('contratti.edit', $contratto->id) }}"
                               class="text-blue-600 hover:text-blue-900 mr-3">
                                Modifica
                            </a>
                            <form action="{{ route('contratti.destroy', $contratto->id) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Sei sicuro di voler eliminare questo contratto? Verranno eliminate anche tutte le scadenze associate.');">
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
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            Nessun contratto trovato
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginazione -->
        <div class="mt-6">
            {{ $contratti->withQueryString()->links() }}
        </div>
    </div>
@endsection