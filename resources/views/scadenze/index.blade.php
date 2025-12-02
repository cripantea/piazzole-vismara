@extends('layout')

@section('title', 'Scadenze')

@section('content')
    <div class="bg-white rounded-lg shadow-sm p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Scadenze</h1>
        </div>

        <!-- Messaggi di successo -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Filtri -->
        <form method="GET" action="{{ route('scadenze.index') }}" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-4">
                <!-- Filtro Cliente -->
                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Cliente
                    </label>
                    <select name="cliente_id"
                            id="cliente_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">Tutti</option>
                        @foreach($clienti as $cliente)
                            <option value="{{ $cliente->id }}" {{ request('cliente_id') == $cliente->id ? 'selected' : '' }}>
                                {{ $cliente->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filtro Piazzola -->
                <div>
                    <label for="piazzola_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Piazzola
                    </label>
                    <select name="piazzola_id"
                            id="piazzola_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">Tutte</option>
                        @foreach($piazzole as $piazzola)
                            <option value="{{ $piazzola->id }}" {{ request('piazzola_id') == $piazzola->id ? 'selected' : '' }}>
                                {{ $piazzola->identificativo }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filtro Data Specifica -->
                <div>
                    <label for="data" class="block text-sm font-medium text-gray-700 mb-1">
                        Data
                    </label>
                    <input type="date"
                           name="data"
                           id="data"
                           value="{{ request('data') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>

                <!-- Filtro Mese -->
                <div>
                    <label for="mese" class="block text-sm font-medium text-gray-700 mb-1">
                        Mese
                    </label>
                    <select name="mese"
                            id="mese"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">Tutti</option>
                        @for($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}" {{ request('mese') == $i ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($i)->format('F') }}
                            </option>
                        @endfor
                    </select>
                </div>

                <!-- Filtro Anno (se filtro mese è attivo) -->
                <div>
                    <label for="anno" class="block text-sm font-medium text-gray-700 mb-1">
                        Anno
                    </label>
                    <select name="anno"
                            id="anno"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">-</option>
                        @for($y = date('Y') - 2; $y <= date('Y') + 2; $y++)
                            <option value="{{ $y }}" {{ request('anno', date('Y')) == $y ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endfor
                    </select>
                </div>

                <!-- Filtro Stato Pagamento -->
                <div>
                    <label for="stato_pagamento" class="block text-sm font-medium text-gray-700 mb-1">
                        Stato
                    </label>
                    <select name="stato_pagamento"
                            id="stato_pagamento"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">Tutti</option>
                        <option value="pagato" {{ request('stato_pagamento') === 'pagato' ? 'selected' : '' }}>
                            Pagato
                        </option>
                        <option value="non_pagato" {{ request('stato_pagamento') === 'non_pagato' ? 'selected' : '' }}>
                            Non Pagato
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2 items-center">
                <!-- Checkbox Scadute -->
                <div class="flex items-center">
                    <input type="checkbox"
                           name="scadute"
                           id="scadute"
                           value="1"
                           {{ request('scadute') == '1' ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="scadute" class="ml-2 text-sm font-medium text-gray-700">
                        Solo Scadute
                    </label>
                </div>

                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors text-sm">
                    Filtra
                </button>

                @if(request()->hasAny(['cliente_id', 'piazzola_id', 'data', 'mese', 'anno', 'stato_pagamento', 'scadute']))
                    <a href="{{ route('scadenze.index') }}"
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition-colors text-sm">
                        Reset Filtri
                    </a>
                @endif
            </div>
        </form>

        <!-- Statistiche rapide -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-sm text-blue-600 font-medium">Totale Scadenze</div>
                <div class="text-2xl font-bold text-blue-900">{{ $scadenze->total() }}</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-sm text-green-600 font-medium">Importo Totale</div>
                <div class="text-2xl font-bold text-green-900">
                    € {{ number_format($scadenze->sum('importo'), 2, ',', '.') }}
                </div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <div class="text-sm text-yellow-600 font-medium">Non Pagate</div>
                <div class="text-2xl font-bold text-yellow-900">
                    {{ $scadenze->whereNull('data_pagamento')->count() }}
                </div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg">
                <div class="text-sm text-red-600 font-medium">Scadute</div>
                <div class="text-2xl font-bold text-red-900">
                    {{ $scadenze->filter(fn($s) => $s->isScaduta())->count() }}
                </div>
            </div>
        </div>

        <!-- Tabella -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('scadenze.index', array_merge(request()->all(), ['sort_by' => 'numero_rata', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            #
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Cliente
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Piazzola
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('scadenze.index', array_merge(request()->all(), ['sort_by' => 'data', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            Data Scadenza
                            @if(request('sort_by', 'data') === 'data')
                                <span>{{ request('sort_order', 'asc') === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('scadenze.index', array_merge(request()->all(), ['sort_by' => 'importo', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="hover:text-gray-700">
                            Importo
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Data Pagamento
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Azioni
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                @forelse($scadenze as $scadenza)
                    <tr class="hover:bg-gray-50 {{ $scadenza->isScaduta() ? 'bg-red-50' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $scadenza->numero_rata }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $scadenza->contratto->cliente->nome }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $scadenza->contratto->piazzola->identificativo }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $scadenza->data->format('d/m/Y') }}
                            @if($scadenza->isScaduta())
                                <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Scaduta
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                            € {{ number_format($scadenza->importo, 2, ',', '.') }}
                            @if($scadenza->importo_originale && $scadenza->importo_originale > $scadenza->importo)
                                <span class="text-xs text-blue-600">
                                    (parz. di {{ number_format($scadenza->importo_originale, 2, ',', '.') }})
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($scadenza->isPagata())
                                <span class="text-green-600 font-medium">
                                    {{ $scadenza->data_pagamento->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-400">Non pagata</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            @if($scadenza->isPagata())
                                <form action="{{ route('scadenze.rimuovi-pagamento', $scadenza) }}"
                                      method="POST"
                                      class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="text-yellow-600 hover:text-yellow-900"
                                            onclick="return confirm('Rimuovere il pagamento?');">
                                        Annulla Pagamento
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('scadenze.paga', $scadenza) }}"
                                      method="POST"
                                      class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="text-green-600 hover:text-green-900">
                                        Segna Pagata
                                    </button>
                                </form>
                                <a href="{{ route('scadenze.pagamento-parziale', $scadenza) }}"
                                   class="text-blue-600 hover:text-blue-900">
                                    Pag. Parziale
                                </a>
                            @endif
                        </td>
                    @if($scadenza->scadenza_originale_id)
                        </tr>
                        <tr class="bg-blue-50">
                            <td class="px-6 py-4 text-xs text-blue-700" colspan="7">
                                ↳ Rimanenza della Rata {{ $scadenza->numero_rata }}
                            </td>
                        </tr>
                    @endif
                    </tr>

                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            Nessuna scadenza trovata
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginazione -->
        <div class="mt-6">
            {{ $scadenze->withQueryString()->links() }}
        </div>
    </div>
@endsection