@extends('layout')

@section('title', 'Nuovo Contratto')

@section('content')
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-sm p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Nuovo Contratto</h1>

        <form action="{{ route('contratti.store') }}" method="POST" id="contrattoForm">
            @csrf

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
                        <option value="">Seleziona una piazzola</option>
                        @foreach($piazzole as $piazzola)
                            <option value="{{ $piazzola->id }}" {{ old('piazzola_id') == $piazzola->id ? 'selected' : '' }}>
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
                        <option value="">Seleziona un cliente</option>
                        @foreach($clienti as $cliente)
                            <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>
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
                           value="{{ old('data_inizio', '2026-01-01') }}"
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
                           value="{{ old('data_fine', '2026-12-31') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('data_fine') border-red-500 @enderror"
                           required>
                    @error('data_fine')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-6">
                <!-- Valore -->
                <div>
                    <label for="valore" class="block text-sm font-medium text-gray-700 mb-2">
                        Valore Totale (€) *
                    </label>
                    <input type="number"
                           name="valore"
                           id="valore"
                           step="0.01"
                           value="{{ old('valore', '1000') }}"
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
                           value="{{ old('numero_rate', 4) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('numero_rate') border-red-500 @enderror"
                           required>
                    @error('numero_rate')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Pulsante Genera Scadenze -->
            <div class="mb-6">
                <button type="button"
                        id="generaScadenzeBtn"
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Genera Scadenze
                </button>
                <p class="text-sm text-gray-600 mt-2">
                    Le scadenze saranno distribuite uniformemente tra la data di inizio e la data di fine del contratto
                </p>
            </div>

            <!-- Tabella Scadenze -->
            <div id="scadenzeContainer" class="hidden">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Scadenze Generate</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Puoi modificare le date e gli importi delle singole rate prima di salvare
                </p>
                <div class="overflow-x-auto mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rata</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Scadenza</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Importo (€)</th>
                        </tr>
                        </thead>
                        <tbody id="scadenzeTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Le righe verranno generate dinamicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        id="submitBtn"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                        disabled>
                    Salva Contratto
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
            const generaBtn = document.getElementById('generaScadenzeBtn');
            const container = document.getElementById('scadenzeContainer');
            const tableBody = document.getElementById('scadenzeTableBody');
            const submitBtn = document.getElementById('submitBtn');

            generaBtn.addEventListener('click', async function() {
                const dataInizio = document.getElementById('data_inizio').value;
                const dataFine = document.getElementById('data_fine').value;
                const valore = document.getElementById('valore').value;
                const numeroRate = document.getElementById('numero_rate').value;

                if (!dataInizio || !dataFine || !valore || !numeroRate) {
                    alert('Compila tutti i campi prima di generare le scadenze');
                    return;
                }

                if (new Date(dataFine) <= new Date(dataInizio)) {
                    alert('La data di fine deve essere successiva alla data di inizio');
                    return;
                }

                try {
                    const response = await fetch('{{ route("contratti.genera-scadenze") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            data_inizio: dataInizio,
                            data_fine: dataFine,
                            valore: valore,
                            numero_rate: numeroRate
                        })
                    });

                    const data = await response.json();

                    // Pulisci la tabella
                    tableBody.innerHTML = '';

                    // Aggiungi le righe
                    data.scadenze.forEach((scadenza, index) => {
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50';

                        row.innerHTML = `
    <td class="px-6 py-4 text-sm font-medium text-gray-900">Rata ${scadenza.numero_rata}</td>
    <td class="px-6 py-4">
        <input type="date"
               name="scadenze[${index}][data]"
               value="${scadenza.data}"
               class="px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
               required>
    </td>
    <td class="px-6 py-4">
        <input type="number"
               name="scadenze[${index}][importo]"
               value="${scadenza.importo}"
               step="0.01"
               class="px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 w-32"
               required>
    </td>
`;                        tableBody.appendChild(row);
                    });

                    // Mostra il container e abilita il submit
                    container.classList.remove('hidden');
                    submitBtn.disabled = false;

                } catch (error) {
                    alert('Errore nella generazione delle scadenze');
                    console.error(error);
                }
            });
        });
    </script>
@endsection