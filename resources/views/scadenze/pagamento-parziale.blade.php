@extends('layout')

@section('title', 'Pagamento Parziale')

@section('content')
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-sm p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Pagamento Parziale</h1>

        <!-- Info scadenza -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h2 class="font-semibold text-blue-900 mb-3">Dettagli Scadenza</h2>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="text-blue-700">Cliente:</span>
                    <span class="font-medium text-blue-900">{{ $scadenza->contratto->cliente->nome }}</span>
                </div>
                <div>
                    <span class="text-blue-700">Piazzola:</span>
                    <span class="font-medium text-blue-900">{{ $scadenza->contratto->piazzola->identificativo }}</span>
                </div>
                <div>
                    <span class="text-blue-700">Rata:</span>
                    <span class="font-medium text-blue-900">{{ $scadenza->numero_rata }}</span>
                </div>
                <div>
                    <span class="text-blue-700">Data Scadenza:</span>
                    <span class="font-medium text-blue-900">{{ $scadenza->data->format('d/m/Y') }}</span>
                </div>
                <div class="col-span-2">
                    <span class="text-blue-700">Importo Totale:</span>
                    <span class="font-bold text-blue-900 text-lg">€ {{ number_format($scadenza->importo, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Info -->
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
            <strong>ℹ️ Come funziona:</strong>
            <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                <li>Questa scadenza sarà ridotta all'importo pagato e chiusa</li>
                <li>Verrà creata una nuova scadenza per la rimanenza</li>
            </ul>
        </div>

        <!-- Form pagamento parziale -->
        <form action="{{ route('scadenze.pagamento-parziale.store', $scadenza) }}" method="POST" id="pagamentoParziale">
            @csrf

            <div class="mb-6">
                <label for="importo_pagato" class="block text-sm font-medium text-gray-700 mb-2">
                    Importo Pagato (€) *
                </label>
                <input type="number"
                       name="importo_pagato"
                       id="importo_pagato"
                       step="0.01"
                       min="0.01"
                       max="{{ $scadenza->importo - 0.01 }}"
                       value="{{ old('importo_pagato') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('importo_pagato') border-red-500 @enderror"
                       required
                       autofocus>
                @error('importo_pagato')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
                <p class="text-sm text-gray-600 mt-1">
                    Deve essere inferiore a € {{ number_format($scadenza->importo, 2, ',', '.') }}
                </p>
            </div>

            <div class="mb-6">
                <label for="data_nuova_scadenza" class="block text-sm font-medium text-gray-700 mb-2">
                    Data Nuova Scadenza (per la rimanenza) *
                </label>
                <input type="date"
                       name="data_nuova_scadenza"
                       id="data_nuova_scadenza"
                       value="{{ old('data_nuova_scadenza', $scadenza->data->copy()->addDay()->format('Y-m-d')) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('data_nuova_scadenza') border-red-500 @enderror"
                       required>
                @error('data_nuova_scadenza')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
                <p class="text-sm text-gray-600 mt-1">
                    Default: {{ $scadenza->data->copy()->addDay()->format('d/m/Y') }} (giorno dopo la scadenza corrente)
                </p>
            </div>

            <!-- Preview calcoli -->
            <div id="previewCalcoli" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 hidden">
                <h3 class="font-semibold text-green-900 mb-3">📊 Riepilogo Operazione</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-green-700">Importo scadenza corrente:</span>
                        <span class="font-medium text-green-900">€ {{ number_format($scadenza->importo, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-green-700">Importo che stai pagando ora:</span>
                        <span class="font-medium text-green-900" id="importoPagatoPreview">€ 0,00</span>
                    </div>
                    <div class="border-t border-green-300 my-2"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-green-700 font-semibold">✓ Questa scadenza:</span>
                        <div class="text-right">
                            <span class="font-bold text-green-900" id="importoPagatoPreview2">€ 0,00</span>
                            <span class="text-xs text-green-600 block">PAGATA</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-green-700 font-semibold">+ Nuova scadenza:</span>
                        <div class="text-right">
                            <span class="font-bold text-orange-900" id="rimanenzaPreview">€ 0,00</span>
                            <span class="text-xs text-orange-600 block">DA PAGARE</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Registra Pagamento Parziale
                </button>
                <a href="{{ route('scadenze.index') }}"
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition-colors">
                    Annulla
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const importoPagatoInput = document.getElementById('importo_pagato');
            const previewDiv = document.getElementById('previewCalcoli');
            const importoPagatoSpan = document.getElementById('importoPagatoPreview');
            const importoPagatoSpan2 = document.getElementById('importoPagatoPreview2');
            const rimanenzaSpan = document.getElementById('rimanenzaPreview');

            const importoTotale = {{ $scadenza->importo }};

            function formatEuro(numero) {
                return '€ ' + numero.toFixed(2).replace('.', ',');
            }

            function aggiornaPreview() {
                const importoPagato = parseFloat(importoPagatoInput.value) || 0;

                if (importoPagato > 0 && importoPagato < importoTotale) {
                    const rimanenza = importoTotale - importoPagato;

                    importoPagatoSpan.textContent = formatEuro(importoPagato);
                    importoPagatoSpan2.textContent = formatEuro(importoPagato);
                    rimanenzaSpan.textContent = formatEuro(rimanenza);

                    previewDiv.classList.remove('hidden');
                } else {
                    previewDiv.classList.add('hidden');
                }
            }

            importoPagatoInput.addEventListener('input', aggiornaPreview);

            // Aggiorna all'avvio se c'è un valore
            if (importoPagatoInput.value) {
                aggiornaPreview();
            }
        });
    </script>
@endsection