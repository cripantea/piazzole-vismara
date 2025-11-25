<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ContrattoController;
use App\Http\Controllers\PiazzolaController;
use App\Http\Controllers\ScadenzaController;
use App\Models\Cliente;
use App\Models\Contratto;
use App\Models\Scadenza;
use Illuminate\Support\Facades\Route;

// Homepage -> Scadenze
Route::get('/', [ScadenzaController::class, 'index'])->name('home');

// Scadenze
Route::get('scadenze', [ScadenzaController::class, 'index'])->name('scadenze.index');
Route::put('scadenze/{scadenza}', [ScadenzaController::class, 'update'])->name('scadenze.update');
Route::post('scadenze/{scadenza}/paga', [ScadenzaController::class, 'segnaComePagata'])->name('scadenze.paga');
Route::post('scadenze/{scadenza}/rimuovi-pagamento', [ScadenzaController::class, 'rimuoviPagamento'])->name('scadenze.rimuovi-pagamento');

Route::resource('contratti', ContrattoController::class);
Route::post('contratti/genera-scadenze', [ContrattoController::class, 'generaScadenze'])
    ->name('contratti.genera-scadenze');
Route::post('contratti/{contratto}/chiudi', [ContrattoController::class, 'chiudi'])
    ->name('contratti.chiudi');
Route::post('contratti/{contratto}/rinnova', [ContrattoController::class, 'rinnova'])
    ->name('contratti.rinnova');

Route::post('contratti/genera-scadenze', [ContrattoController::class, 'generaScadenze'])
    ->name('contratti.genera-scadenze');
Route::resource('clienti', ClienteController::class);

Route::resource('piazzole', PiazzolaController::class);
