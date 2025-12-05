@extends('layout')

@section('title', 'Pagamento Multiplo - Cifra Libera')

@section('content')
    <div class="bg-white rounded-lg shadow-sm p-6 max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Pagamento Cifra Libera</h1>
            <div class="text-sm text-gray-600">
                <span class="font-semibold">Contratto:</span>
                {{ $contratto->piazzola->identificativo }} - {{ $contratto->cliente->nome }}
            </div>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('scadenze.pagamento-multiplo.store', $scadenzaPartenza) }}"
              method="POST"
              id="pagamentoMultiploForm">
            @csrf

            <!-- Importo Totale da Distribuire -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <label for="importo_totale" class="block text-sm font-medium text-gray-700 mb-2">
                    Importo Totale da Distribuire *
                </label>
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold text-gray-800">€</span>
                    <input type="number"
                           name="importo_totale"
                           id="importo_totale"
                           step="0.01"
                           min="0.01"
                           value="{{ old('importo_totale') }}"
                           required
                           class="text-2xl font-bold px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="0.00"
                           title="Doppio-click per distribuzione automatica">
                </div>
                <div id="importo_residuo" class="mt-2 text-sm font-medium text-gray-600"></div>
                <div class="mt-2 text-xs text-blue-600">
                    💡 <strong>Suggerimento:</strong> Doppio-click sull'importo per distribuzione automatica proporzionale
                </div>
            </div>

            <!-- Tabella Scadenze Non Pagate -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Seleziona Scadenze da Pagare</h2>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    <input type="checkbox"
                                           id="seleziona_tutte"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rata</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Scadenza</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Importo Dovuto</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Importo da Pagare</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($scadenzeNonPagate as $scadenza)
                                <tr class="hover:bg-gray-50 scadenza-row {{ $scadenza->isScaduta() ? 'bg-red-50' : '' }}"
                                    data-importo-dovuto="{{ $scadenza->importo }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox"
                                               name="scadenze[{{ $scadenza->id }}][selezionata]"
                                               value="1"
                                               class="scadenza-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                               data-scadenza-id="{{ $scadenza->id }}">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        Rata {{ $scadenza->numero_rata }}
                                        @if($scadenza->isScaduta())
                                            <span class="ml-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Scaduta
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $scadenza->data->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 font-medium">
                                        € {{ number_format($scadenza->importo, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-600">€</span>
                                            <input type="number"
                                                   name="scadenze[{{ $scadenza->id }}][importo]"
                                                   class="importo-pagamento w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                                   step="0.01"
                                                   min="0"
                                                   max="{{ $scadenza->importo }}"
                                                   placeholder="0.00"
                                                   data-scadenza-id="{{ $scadenza->id }}"
                                                   data-importo-max="{{ $scadenza->importo }}"
                                                   disabled>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Max: € {{ number_format($scadenza->importo, 2, ',', '.') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($scadenzeNonPagate->isEmpty())
                    <div class="text-center py-8 text-gray-500">
                        Nessuna scadenza non pagata per questo contratto
                    </div>
                @endif
            </div>

            <!-- Data per Nuove Scadenze Parziali -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <label for="data_nuove_scadenze" class="block text-sm font-medium text-gray-700 mb-2">
                    Data per Scadenze Rimanenti (pagamenti parziali)
                </label>
                <input type="date"
                       name="data_nuove_scadenze"
                       id="data_nuove_scadenze"
                       value="{{ old('data_nuove_scadenze', $dataSuggerita) }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">
                    Le scadenze pagate parzialmente creeranno nuove scadenze con questa data
                </p>
            </div>

            <!-- Azioni -->
            <div class="flex justify-between items-center pt-6 border-t">
                <a href="{{ route('scadenze.index') }}"
                   class="text-gray-600 hover:text-gray-800">
                    ← Annulla
                </a>
                <button type="submit"
                        id="submit_button"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                    Conferma Pagamento
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('pagamentoMultiploForm');
            const importoTotaleInput = document.getElementById('importo_totale');
            const importoResiduoDiv = document.getElementById('importo_residuo');
            const submitButton = document.getElementById('submit_button');
            const selezionaTutteCheckbox = document.getElementById('seleziona_tutte');
            const scadenzeCheckboxes = document.querySelectorAll('.scadenza-checkbox');
            const importiPagamento = document.querySelectorAll('.importo-pagamento');

            // Seleziona/Deseleziona tutte
            selezionaTutteCheckbox.addEventListener('change', function() {
                scadenzeCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    const importoInput = document.querySelector(`.importo-pagamento[data-scadenza-id="${checkbox.dataset.scadenzaId}"]`);
                    const row = checkbox.closest('tr');

                    importoInput.disabled = !this.checked;
                    if (this.checked) {
                        importoInput.value = '';
                        row.classList.add('bg-blue-50', 'border-l-4', 'border-blue-500');
                    } else {
                        row.classList.remove('bg-blue-50', 'border-l-4', 'border-blue-500');
                    }
                });
                calcolaResiduo();
            });

            // Gestione checkbox singola
            scadenzeCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const importoInput = document.querySelector(`.importo-pagamento[data-scadenza-id="${this.dataset.scadenzaId}"]`);
                    const row = this.closest('tr');

                    importoInput.disabled = !this.checked;
                    if (!this.checked) {
                        importoInput.value = '';
                        row.classList.remove('bg-blue-50', 'border-l-4', 'border-blue-500');
                    } else {
                        row.classList.add('bg-blue-50', 'border-l-4', 'border-blue-500');
                    }
                    calcolaResiduo();
                });
            });

            // Gestione input importi
            importiPagamento.forEach(input => {
                input.addEventListener('input', calcolaResiduo);
            });

            // Gestione cambio importo totale
            importoTotaleInput.addEventListener('input', calcolaResiduo);

            function calcolaResiduo() {
                const importoTotale = parseFloat(importoTotaleInput.value) || 0;
                let totaleDistribuito = 0;

                importiPagamento.forEach(input => {
                    if (!input.disabled) {
                        const valore = parseFloat(input.value) || 0;
                        totaleDistribuito += valore;
                    }
                });

                const residuo = importoTotale - totaleDistribuito;

                // Aggiorna visualizzazione
                if (importoTotale > 0) {
                    importoResiduoDiv.innerHTML = `
                        <span class="font-semibold">Distribuito:</span> € ${totaleDistribuito.toFixed(2)} / € ${importoTotale.toFixed(2)}
                        <span class="ml-4 font-semibold ${residuo < 0 ? 'text-red-600' : 'text-gray-600'}">
                            Residuo: € ${residuo.toFixed(2)}
                        </span>
                    `;

                    // Abilita submit solo se residuo = 0 e almeno una scadenza selezionata
                    const almenoUnaSelezionata = Array.from(scadenzeCheckboxes).some(cb => cb.checked);
                    submitButton.disabled = !(Math.abs(residuo) < 0.01 && almenoUnaSelezionata && totaleDistribuito > 0);
                } else {
                    importoResiduoDiv.innerHTML = '';
                    submitButton.disabled = true;
                }
            }

            // Auto-distribuzione proporzionale (doppio click su importo totale)
            importoTotaleInput.addEventListener('dblclick', function() {
                const importoTotale = parseFloat(this.value) || 0;
                if (importoTotale <= 0) return;

                // Calcola totale dovuto dalle scadenze selezionate
                let totaleDovuto = 0;
                const scadenzeSelezionate = [];

                scadenzeCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const importoInput = document.querySelector(`.importo-pagamento[data-scadenza-id="${checkbox.dataset.scadenzaId}"]`);
                        const importoMax = parseFloat(importoInput.dataset.importoMax);
                        totaleDovuto += importoMax;
                        scadenzeSelezionate.push({ input: importoInput, max: importoMax });
                    }
                });

                if (scadenzeSelezionate.length === 0) {
                    alert('Seleziona almeno una scadenza prima di distribuire automaticamente');
                    return;
                }

                // Distribuisci proporzionalmente
                if (importoTotale >= totaleDovuto) {
                    // Pagamento totale di tutte le scadenze
                    scadenzeSelezionate.forEach(s => {
                        s.input.value = s.max.toFixed(2);
                    });
                } else {
                    // Distribuzione proporzionale
                    var resto=importoTotale;
                    scadenzeSelezionate.forEach(s => {
                        if(resto >= s.max)
                        {
                            s.input.value = s.max.toFixed(2);
                            resto-=s.max;
                        } else {
                            s.input.value = resto.toFixed(2);
                            resto=0;
                            return;
                        }
                        // const percentuale = s.max / totaleDovuto;
                        // s.input.value = (importoTotale * percentuale).toFixed(2);
                        // console.log(s, totaleDovuto, importoTotale, percentuale);
                    });
                }

                calcolaResiduo();
            });

            // Calcolo iniziale
            calcolaResiduo();
        });
    </script>
    @endpush
@endsection

