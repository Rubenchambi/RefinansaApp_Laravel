<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ListaNegraController;
use App\Http\Controllers\Api\ActualizarRequerimientosController;
use App\Http\Controllers\Api\SubirAsignacionesController;
use App\Http\Controllers\Api\GenerarPredictivoController;

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

// Endpoint para el proceso de previsualización y carga
Route::post('/subir-asignacion', [SubirAsignacionesController::class, 'subirAsignacion']);
// Endpoint del monitor para refrescar la tabla dinámica en vivo
Route::get('/subir-asignacion/monitor', [SubirAsignacionesController::class, 'monitorAsignacion']);

Route::prefix('predictivo')->group(function () {
    Route::get('/opciones-filtros', [GenerarPredictivoController::class, 'obtenerOpcionesFiltros']);
    Route::post('/cargar-dnis', [GenerarPredictivoController::class, 'cargarDnis']);
    Route::post('/generar-devalix', [GenerarPredictivoController::class, 'generarDevalix']);
    Route::post('/generar-uncontac', [GenerarPredictivoController::class, 'generarUncontac']);
    Route::post('/generar-excel', [GenerarPredictivoController::class, 'generarExcel']);
});