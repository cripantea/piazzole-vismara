<?php

use App\Models\Cliente;
use App\Models\Contratto;
use App\Models\Scadenza;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $listaScadenze=Scadenza::all();
    return view('scadenze.index', compact('listaScadenze'));
})->name('scadenze.index');


Route::get('/contratti', function () {
    $listaContratti=Contratto::all();
    return view('contratti.index', compact('listaContratti'));
})->name('contratti.index');

Route::get('/clienti', function () {
    $listaClienti=Cliente::all();
    return view('clienti.index', compact('listaClienti'));
})->name('clienti.index');

Route::get('/piazzuole', function () {
    $piazzuole=\App\Models\Piazzuola::all();
    return view('piazzuole.index', compact('piazzuole'));
})->name('piazzuole.index');
