@extends('layout')

@section('title', 'Chiusura Contratto')

@section('content')
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-sm p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Chiusura Contratto</h1>

        <!-- Info contratto -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h2 class="font-semibold text-blue-900 mb-2">Dettagli Contratto</h2>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                    <span class="text-blue-700">Piazzola:</span>
                    <span class="font-medium text-blue-900">{{ $contratto->piazzola->identificativo }}</span>
                </div>
                <div>
                    <span class="text-blue-700">Cliente:</span>
                    <span class="font-medium text-blue-900">{{ $contratto->cliente->nome }}</span>
                </div>
                <div>
                    <span class="text-blue-700">Periodo:</span>
                    <span class="font-medium text-blue-900">
                    {{ $contratto->data_inizio->format('d/m/Y') }} - {{ $contratto->data_fine->format('d/m/Y') }}
                </span>
                </div>
                <div>
                    <span class="text-blue-700">Valore:</span>
                    <span class="font-medium text-blue-900">€ {{ number_format($contratto->valore, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Scadenze pagate e non pagate -->
        <div class="mb-6">
            <h3 class="font-semibold text-gray-800 mb-3">Situazione Scadenze</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="text-sm text-green-700">Scadenze Pagate</div>
                    <div class="text-2xl font-bold text-green-900">
                        {{ $contratto->scadenzePagate()->count() }}
                    </div>
                    <div class="text-xs text-green-600">
                        € {{ number_format($contratto->scadenzePagate()->sum('importo'), 2, ',', '.') }}
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <div class="text-sm text-yellow-700">Scadenze Non Pagate</div>
                    <div class="text-2xl font-bold text-yellow-900">
                        {{ $contratto->scadenzeNonPagate()->count() }}
                    </div>
                    <div class="text-xs text-yellow-600">
                        € {{ number_format($contratto->scadenzeNonPagate()->sum('importo'), 2, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Warning -->
        @if($contratto->scadenzeNonPagate()->count() > 0)
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
                <strong>⚠️ Attenzione:</strong> La chiusura del contratto eliminerà tutte le scadenze non pagate successive alla data di chiusura.
            </div>
        @endif

        <!-- Elenco scadenze non pagate che verranno eliminate -->
        @if($contratto->scadenzeNonPagate()->count() > 0)
            <div class="mb-6">
                <h3 class="font-semibold text-gray-800 mb-3">Scadenze Non Pagate</h3>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Rata</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Data</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Importo</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($contratto->scadenzeNonPagate as $scadenza)
                            <tr class="text-sm">
                                <td class="px-4 py-2 text-gray-900">Rata {{ $scadenza->numero_rata }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $scadenza->data->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-gray-900 font-medium">
                                    € {{ number_format($scadenza->importo, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Form chiusura -->
        <form action="{{ route('contratti.chiudi', $contratto->id) }}" method="POST" id="chiusuraForm">
            @csrf

            <div class="mb-6">
                <label for="data_chiusura" class="block text-sm font-medium text-gray-700 mb-2">
                    Data di Chiusura *
                </label>
                <input type="date"
                       name="data_chiusura"
                       id="data_chiusura"
                       value="{{ old('data_chiusura', now()->format('Y-m-d')) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('data_chiusura') border-red-500 @enderror"
                       required>
                @error('data_chiusura')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
                <p class="text-sm text-gray-600 mt-1">
                    Le scadenze non pagate successive a questa data verranno eliminate.
                </p>
            </div>

            <!-- Preview scadenze da eliminare -->
            <div id="previewEliminazione" class="hidden mb-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h4 class="font-semibold text-red-900 mb-2">Scadenze che verranno eliminate:</h4>
                    <ul id="listaEliminazione" class="text-sm text-red-800 space-y-1">
                        <!-- Popolato dinamicamente -->
                    </ul>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-colors"
                        onclick="return confirm('Sei sicuro di voler chiudere questo contratto? Le scadenze non pagate successive verranno eliminate e questa azione non può essere annullata!');">
                    Chiudi Contratto
                </button>
                <a href="{{ route('contratti.index') }}"
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition-colors">
                    Annulla
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dataChiusuraInput = document.getElementById('data_chiusura');
            const previewDiv = document.getElementById('previewEliminazione');
            const listaUl = document.getElementById('listaEliminazione');

            // Scadenze non pagate dal server
            const scadenzeNonPagate = {!! json_encode($contratto->scadenzeNonPagate->map(function($s) {
        return [
            'numero_rata' => $s->numero_rata,
            'data' => $s->data->format('Y-m-d'),
            'data_formatted' => $s->data->format('d/m/Y'),
            'importo' => (float) $s->importo
        ];
    })) !!};

            // Funzione JavaScript per formattare l'importo
            function formatImporto(importo) {
                return importo.toFixed(2).replace('.', ',');
            }

            function aggiornaPreview() {
                const dataChiusura = new Date(dataChiusuraInput.value);

                // Filtra le scadenze che verranno eliminate
                const scadenzeDaEliminare = scadenzeNonPagate.filter(s => {
                    const dataScadenza = new Date(s.data);
                    return dataScadenza > dataChiusura;
                });

                if (scadenzeDaEliminare.length > 0) {
                    listaUl.innerHTML = '';
                    scadenzeDaEliminare.forEach(s => {
                        const li = document.createElement('li');
                        li.textContent = `Rata ${s.numero_rata} - ${s.data_formatted} - € ${formatImporto(s.importo)}`;
                        listaUl.appendChild(li);
                    });
                    previewDiv.classList.remove('hidden');
                } else {
                    previewDiv.classList.add('hidden');
                }
            }

            dataChiusuraInput.addEventListener('change', aggiornaPreview);

            // Aggiorna all'avvio se c'è una data preselezionata
            if (dataChiusuraInput.value) {
                aggiornaPreview();
            }
        });
    </script>
@endsection