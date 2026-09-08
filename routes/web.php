<?php

use App\Http\Controllers\BibliografiaController;
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
    Route::match(['get', 'post'], 'bibliografia', [BibliografiaController::class, 'bibliografia'])->name('bibliografia.bibliografia');
    Route::match(['get', 'post'], 'ementa', [BibliografiaController::class, 'ementa'])->name('bibliografia.ementa');
});

// Permite usar Gate::check('user')na view 404
Route::fallback(function () {
    return view('errors.404');
});
