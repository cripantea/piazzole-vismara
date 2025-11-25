@extends('layout')

@section('title', 'Modifica Contratto')

@section('content')
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Modifica Contratto</h1>

            <div class="flex gap-2">
                @if($contratto->tutteScadenzePagate() && $contratto->stato !== 'completato')
                    <form action="{{ route('contratti.chiudi', $contratto) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition-colors text-sm"
                                onclick="return confirm('Chiudere questo contratto?');">
                            Chiudi Contratto
                        </button>
                    </form>

                    <form action="{{ route('contratti.rinnova', $contratto) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors text-sm"
                                onclick="return confirm('Rinnovare questo contratto per lo stesso periodo?');">
                            Rinnova Contratto
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if($contratto->hasScadenzePagate())
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                ℹ️ Questo contratto ha scadenze già pagate. Le scadenze pagate non possono essere modificate.
            </div>
        @endif

        <form action="{{ route('contratti.update', $contratto) }}" method="POST" id="contrattoForm">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-6 mb-6">
                <!-- Piazzola -->
                <div>
                    <label for="piazzola_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Piazzola *
                    </label>
                    <select name="piazzola_id"
                            id="piazzola_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('piazzola_id') border-red-500 @enderror"
                            required>
                        @foreach($piazzole as $piazzola)
                            <option value="{{ $piazzola->id }}" {{ old('piazzola_id', $contratto->piazzola_id) == $piazzola->id ? 'selected' : '' }}>
                                {{ $piazzola->identificativo }} - {{ $piazzola->nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('piazzola_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Cliente -->
                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Cliente *
                    </label>
                    <select name="cliente_id"
                            id="cliente_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('cliente_id') border-red-500 @enderror"
                            required>
                        @foreach($clienti as $cliente)
                            <option value="{{ $cliente->id }}" {{ old('cliente_id', $contratto->cliente_id) == $cliente->id ? 'selected' : '' }}>
                                {{ $cliente->nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('cliente_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-6">
                <!-- Data Inizio -->
                <div>
                    <label for="data_inizio" class="block text-sm font-medium text-gray-700 mb-2">
                        Data Inizio Contratto *
                    </label>
                    <input type="date"
                           name="data_inizio"
                           id="data_inizio"
                           value="{{ old('data_inizio', $contratto->data_inizio->format('Y-m-d')) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('data_inizio') border-red-500 @enderror"
                           required>
                    @error('data_inizio')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Data Fine -->
                <div>
                    <label for="data_fine" class="block text-sm font-medium text-gray-700 mb-2">
                        Data Fine Contratto *
                    </label>
                    <input type="date"
                           name="data_fine"
                           id="data_fine"
                           value="{{ old('data_fine', $contratto->data_fine->format('Y-m-d')) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('data_fine') border-red-500 @enderror"
                           required>
                    @error('data_fine')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-3 gap-6 mb-6">
                <!-- Valore -->
                <div>
                    <label for="valore" class="block text-sm font-medium text-gray-700 mb-2">
                        Valore Totale (€) *
                    </label>
                    <input type="number"
                           name="valore"
                           id="valore"
                           step="0.01"
                           value="{{ old('valore', $contratto->valore) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('valore') border-red-500 @enderror"
                           required>
                    @error('valore')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Numero Rate -->
                <div>
                    <label for="numero_rate" class="block text-sm font-medium text-gray-700 mb-2">
                        Numero Rate *
                    </label>
                    <input type="number"
                           name="numero_rate"
                           id="numero_rate"
                           min="1"
                           value="{{ old('numero_rate', $contratto->numero_rate) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           readonly
                           disabled>
                    <input type="hidden" name="numero_rate" value="{{ $contratto->numero_rate }}">
                    <p class="text-xs text-gray-500 mt-1">Non modificabile</p>
                </div>

                <!-- Stato -->
                <div>
                    <label for="stato" class="block text-sm font-medium text-gray-700 mb-2">
                        Stato *
                    </label>
                    <select name="stato"
                            id="stato"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required>
                        <option value="attivo" {{ old('stato', $contratto->stato) == 'attivo' ? 'selected' : '' }}>Attivo</option>
                        <option value="sospeso" {{ old('stato', $contratto->stato) == 'sospeso' ? 'selected' : '' }}>Sospeso</option>
                        <option value="completato" {{ old('stato', $contratto->stato) == 'completato' ? 'selected' : '' }}>Completato</option>
                    </select>
                </div>
            </div>

            <!-- Errore validazione scadenze -->
            @error('scadenze')
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ $message }}
            </div>
            @enderror

            <!-- Tabella Scadenze -->
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Scadenze</h2>

                <!-- Alert validazione somma -->
                <div id="validationAlert" class="hidden mb-4"></div>

                <div class="overflow-x-auto mb-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rata</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Scadenza</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Importo (€)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                        </tr>
                        </thead>
                        <tbody id="scadenzeTableBody" class="bg-white divide-y divide-gray-200">
                        @foreach($contratto->scadenze as $scadenza)
                            <tr class="hover:bg-gray-50 {{ $scadenza->isPagata() ? 'bg-green-50' : '' }}">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    Rata {{ $scadenza->numero_rata }}
                                    <input type="hidden" name="scadenze[{{ $loop->index }}][id]" value="{{ $scadenza->id }}">
                                </td>
                                <td class="px-6 py-4">
                                    @if($scadenza->isPagata())
                                        <span class="text-gray-600">{{ $scadenza->data->format('d/m/Y') }}</span>
                                        <input type="hidden" name="scadenze[{{ $loop->index }}][data]" value="{{ $scadenza->data->format('Y-m-d') }}">
                                    @else
                                        <input type="date"
                                               name="scadenze[{{ $loop->index }}][data]"
                                               value="{{ old('scadenze.'.$loop->index.'.data', $scadenza->data->format('Y-m-d')) }}"
                                               class="px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
                                               required>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($scadenza->isPagata())
                                        <span class="text-gray-600">€ {{ number_format($scadenza->importo, 2, ',', '.') }}</span>
                                        <input type="hidden" name="scadenze[{{ $loop->index }}][importo]" value="{{ $scadenza->importo }}">
                                    @else
                                        <input type="number"
                                               name="scadenze[{{ $loop->index }}][importo]"
                                               value="{{ old('scadenze.'.$loop->index.'.importo', $scadenza->importo) }}"
                                               step="0.01"
                                               class="importo-input px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 w-32"
                                               required>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($scadenza->isPagata())
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Pagata il {{ $scadenza->data_pagamento->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Da pagare
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="2" class="px-6 py-3 text-right text-sm font-bold text-gray-700">
                                Totale:
                            </td>
                            <td class="px-6 py-3 text-sm font-bold text-gray-900">
                                € <span id="totaleScadenze">{{ number_format($contratto->scadenze->sum('importo'), 2, '.', '') }}</span>
                            </td>
                            <td></td>
                        </tr>
                        <tr id="differenzaRow" class="hidden">
                            <td colspan="2" class="px-6 py-3 text-right text-sm font-bold text-red-700">
                                Differenza:
                            </td>
                            <td class="px-6 py-3 text-sm font-bold text-red-700">
                                € <span id="differenza">0.00</span>
                            </td>
                            <td></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        id="submitBtn"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Aggiorna Contratto
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
            const submitBtn = document.getElementById('submitBtn');
            const totaleSpan = document.getElementById('totaleScadenze');
            const differenzaSpan = document.getElementById('differenza');
            const differenzaRow = document.getElementById('differenzaRow');
            const validationAlert = document.getElementById('validationAlert');
            const tableBody = document.getElementById('scadenzeTableBody');

            function validaSommaScadenze() {
                const valoreContratto = parseFloat(document.getElementById('valore').value) || 0;
                let sommaScadenze = 0;

                // Calcola la somma di tutte le scadenze (sia editabili che non)
                const importiInputs = tableBody.querySelectorAll('input[name*="[importo]"]');
                importiInputs.forEach(input => {
                    sommaScadenze += parseFloat(input.value) || 0;
                });

                totaleSpan.textContent = sommaScadenze.toFixed(2);

                const differenza = Math.abs(sommaScadenze - valoreContratto);
                differenzaSpan.textContent = differenza.toFixed(2);

                if (differenza > 0.01) {
                    differenzaRow.classList.remove('hidden');
                    validationAlert.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded';
                    validationAlert.textContent = `⚠️ ATTENZIONE: La somma delle scadenze (€ ${sommaScadenze.toFixed(2)}) non corrisponde al valore del contratto (€ ${valoreContratto.toFixed(2)}). Differenza: € ${differenza.toFixed(2)}`;
                    validationAlert.classList.remove('hidden');
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    return false;
                } else {
                    differenzaRow.classList.add('hidden');
                    validationAlert.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded';
                    validationAlert.textContent = '✓ La somma delle scadenze corrisponde al valore del contratto';
                    validationAlert.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    return true;
                }
            }

            // Aggiungi listener agli input editabili
            tableBody.querySelectorAll('.importo-input').forEach(input => {
                input.addEventListener('input', validaSommaScadenze);
            });

            // Valida anche quando cambia il valore del contratto
            document.getElementById('valore').addEventListener('input', validaSommaScadenze);

            // Valida all'avvio
            validaSommaScadenze();

            // Validazione al submit
            document.getElementById('contrattoForm').addEventListener('submit', function(e) {
                if (!validaSommaScadenze()) {
                    e.preventDefault();
                    alert('Impossibile salvare: la somma delle scadenze non corrisponde al valore del contratto!');
                }
            });
        });
    </script>
@endsection