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

        <!-- Alert per rinnovi da confermare -->
        @php
            $contrattiDaConfermare = $contratti->where('rinnovo_automatico', true)->count();
        @endphp

        @if($contrattiDaConfermare > 0)
            <div class="bg-orange-100 border border-orange-400 text-orange-800 px-4 py-3 rounded mb-4">
                <strong>⚠️ Attenzione:</strong> Ci sono {{ $contrattiDaConfermare }} contratt{{ $contrattiDaConfermare > 1 ? 'i' : 'o' }} rinnovati automaticamente che richiedono conferma!
            </div>
        @endif

        <!-- Messaggi di successo -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Messaggi di errore -->
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <!-- Barra di ricerca -->
        <form method="GET" action="{{ route('contratti.index') }}" class="mb-6" id="searchForm">
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
                @if(request('search') || request('solo_aperti') === '0')
                    <a href="{{ route('contratti.index') }}"
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition-colors">
                        Reset
                    </a>
                @endif
            </div>
            <div class="mt-3">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="hidden" name="solo_aperti" value="0">
                    <input type="checkbox"
                           name="solo_aperti"
                           value="1"
                           {{ request('solo_aperti', '1') === '1' || request('solo_aperti', '1') === 1 ? 'checked' : '' }}
                           onchange="this.form.submit()"
                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                    <span class="ml-2 text-sm font-medium text-gray-700">Mostra solo contratti non chiusi</span>
                </label>
            </div>
        </form>

        <!-- Tabella -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('contratti.index', array_merge(request()->all(), ['sort_by' => 'piazzola_id', 'sort_order' => request('sort_by') === 'piazzola_id' && request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            Piazzola
                            @if(request('sort_by') === 'piazzola_id')
                                <span>{{ request('sort_order') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('contratti.index', array_merge(request()->all(), ['sort_by' => 'cliente_id', 'sort_order' => request('sort_by') === 'cliente_id' && request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            Cliente
                            @if(request('sort_by') === 'cliente_id')
                                <span>{{ request('sort_order') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('contratti.index', array_merge(request()->all(), ['sort_by' => 'data_inizio', 'sort_order' => request('sort_by') === 'data_inizio' && request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            Periodo
                            @if(request('sort_by') === 'data_inizio')
                                <span>{{ request('sort_order') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('contratti.index', array_merge(request()->all(), ['sort_by' => 'valore', 'sort_order' => request('sort_by') === 'valore' && request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            Valore
                            @if(request('sort_by') === 'valore')
                                <span>{{ request('sort_order') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Rate
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Prossima Scadenza
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('contratti.index', array_merge(request()->all(), ['sort_by' => 'stato', 'sort_order' => request('sort_by') === 'stato' && request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            Stato
                            @if(request('sort_by') === 'stato')
                                <span>{{ request('sort_order') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Azioni
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                @forelse($contratti as $contratto)
                    <tr class="hover:bg-gray-50 {{ $contratto->isRinnovoAutomatico() ? 'bg-orange-50 border-l-4 border-orange-500' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $contratto->piazzola->identificativo }}
                            @if($contratto->isRinnovoAutomatico())
                                <span class="ml-2 text-xs bg-orange-500 text-white px-2 py-1 rounded">NUOVO</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $contratto->cliente->nome }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $contratto->data_inizio->format('d/m/Y') }} - {{ $contratto->data_fine->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                            € {{ number_format($contratto->valore, 2, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $contratto->scadenzePagate()->count() }} / {{ $contratto->numero_rate }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            @if($contratto->prossimaScadenza)
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $contratto->prossimaScadenza->data->format('d/m/Y') }}</span>
                                    <span class="text-xs text-gray-500">
                                        € {{ number_format($contratto->prossimaScadenza->importo, 2, ',', '.') }}
                                    </span>
                                </div>
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
                            <div class="flex justify-end gap-2 flex-wrap">
                                @if($contratto->isRinnovoAutomatico())
                                    <!-- Pulsante Conferma Rinnovo -->
                                    <form action="{{ route('contratti.conferma-rinnovo', $contratto) }}"
                                          method="POST"
                                          class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-semibold">
                                            ✓ Conferma Rinnovo
                                        </button>
                                    </form>
                                @endif

                                <a href="{{ route('contratti.edit', $contratto) }}"
                                   class="text-blue-600 hover:text-blue-900">
                                    Modifica
                                </a>

                                @if($contratto->stato !== 'completato' && !$contratto->isRinnovoAutomatico())
                                    <a href="{{ route('contratti.chiusura', $contratto) }}"
                                       class="text-yellow-600 hover:text-yellow-900">
                                        Chiudi
                                    </a>
                                @endif

                                @if($contratto->tutteScadenzePagate() && $contratto->stato === 'completato' && !$contratto->isRinnovoAutomatico())
                                    <form action="{{ route('contratti.rinnova', $contratto) }}"
                                          method="POST"
                                          class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-green-600 hover:text-green-900"
                                                onclick="return confirm('Rinnovare questo contratto?');">
                                            Rinnova
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('contratti.destroy', $contratto) }}"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Sei sicuro di voler eliminare questo contratto?{{ $contratto->hasScadenzePagate() ? ' ATTENZIONE: Ci sono scadenze pagate!' : '' }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-red-600 hover:text-red-900 {{ $contratto->hasScadenzePagate() ? 'opacity-50 cursor-not-allowed' : '' }}"
                                            {{ $contratto->hasScadenzePagate() ? 'disabled' : '' }}>
                                        Elimina
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
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