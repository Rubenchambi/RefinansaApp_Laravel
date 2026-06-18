<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ListaNegraController;
use App\Http\Controllers\Api\ActualizarRequerimientosController;

// 🔒 Ruta por defecto de Laravel
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// 🌐 ENDPOINTS LISTA NEGRA
Route::get('/lista-negra', [ListaNegraController::class, 'index']);
Route::post('/lista-negra/previsualizar', [ListaNegraController::class, 'previsualizar']);
Route::post('/lista-negra/cargar', [ListaNegraController::class, 'cargar']);


// 🌐 ENDPOINTS ACTUALIZAR REQUERIMIENTOS
Route::get('/actualizar-requerimientos/monitor', [ActualizarRequerimientosController::class, 'monitor']);
Route::post('/actualizar-requerimientos', [ActualizarRequerimientosController::class, 'procesarCarga']);
Route::get('/actualizar-requerimientos/preview', [ActualizarRequerimientosController::class, 'procesarCarga']);

// ⚡ Acciones rápidas — apuntan directo al controller
Route::post('/actualizar-requerimientos/cargar', [ActualizarRequerimientosController::class, 'procesarCarga']);
Route::post('/actualizar-requerimientos/administrada', [ActualizarRequerimientosController::class, 'procesarCarga']);
Route::post('/actualizar-requerimientos/hipotecario', [ActualizarRequerimientosController::class, 'procesarCarga']);
Route::post('/actualizar-requerimientos/convenio', [ActualizarRequerimientosController::class, 'procesarCarga']);
