<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ListaNegraController;

// 🔒 Esta es la ruta por defecto de Laravel (Déjala tranquila)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// 🌐 TUS ENDPOINTS PÚBLICOS PARA LA LISTA NEGRA (Van afuera)
Route::get('/lista-negra', [ListaNegraController::class, 'index']);
Route::post('/lista-negra/previsualizar', [ListaNegraController::class, 'previsualizar']);
Route::post('/lista-negra/cargar', [ListaNegraController::class, 'cargar']);