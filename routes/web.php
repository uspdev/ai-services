<?php

use App\Http\Controllers\BibliografiaController;
use App\Http\Controllers\EmentaController;
use App\Http\Controllers\TextoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::match(['get', 'post'], 'bibliografia', [BibliografiaController::class, 'index'])->name('bibliografia.index');
    Route::get('bibliografia/dedalus', [BibliografiaController::class, 'dedalus'])->name('bibliografia.dedalus');
    Route::match(['get', 'post'], 'ementa', [EmentaController::class, 'index'])->name('ementa.index');
    Route::match(['get', 'post'], 'texto', [TextoController::class, 'index'])->name('texto.index');
});

// Permite usar Gate::check('user')na view 404
Route::fallback(function () {
    return view('errors.404');
});
