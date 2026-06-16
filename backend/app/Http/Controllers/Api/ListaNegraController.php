<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class ListaNegraController extends Controller
{
    private $tabla = 'Actualizar_Lista_negra';
    // Usamos la conexión por defecto registrada para 'Busqueda_Search'
    private $dbConnection = 'sqlsrv'; 
    public function index(Request $request)
    {
        try {
            $busqueda = $request->query('search', '');
            
            // 1. Calcular KPIs principales en caliente desde SQL Server
            $totalRegistros = DB::connection($this->dbConnection)->table($this->tabla)
                ->count();
            $activos = DB::connection($this->dbConnection)->table($this->tabla)
                ->where('estado', 'LIKE', 'ACT%')
                ->count();

            // 2. Traer registros paginados con filtro de búsqueda por DNI o Teléfono
            $query = DB::connection($this->dbConnection)->table($this->tabla);
            
            if (!empty($busqueda)) {
                $query->where(function($q) use ($busqueda) {
                    $q->where('nro_documento', 'LIKE', "%{$busqueda}%")
                      ->orWhere('telefono', 'LIKE', "%{$busqueda}%");
                });
            }

            // Paginamos de 10 en 10 para no colgar la red local
            $registros = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'kpis' => [
                    'total' => $totalRegistros,
                    'activos' => $activos,
                    'otros' => $totalRegistros - $activos
                ],
                'registros' => $registros
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al leer monitor SQL: ' . $e->getMessage()], 500);
        }
    }

    /**
     * FASE 1: Procesar Excel y devolver la Previsualización al Frontend
     */
    public function previsualizar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx,xls|max:20480' // Máx 20MB
        ]);

        try {
            $file = $request->file('archivo');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            
            if (count($rows) <= 1) {
                return response()->json(['error' => 'El archivo Excel está vacío.'], 422);
            }

            // Mapear cabeceras a índices (Fila 1)
            $headers = array_map('strtolower', array_filter($rows[1]));
            
            $dataPreview = [];
            $duplicadosExcel = 0;
            $vistos = [];
            $resumenEstado = [];

            // Procesar las filas (A partir de la fila 2)
            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i];
                
                // Mapear y Limpiar datos respetando tus criterios (Casting a String y Strip)
                $nroDocumento = isset($row['A']) ? trim((string)$row['A']) : null;
                $telefono = isset($row['B']) ? trim((string)$row['B']) : null;
                $observaciones = isset($row['C']) ? trim((string)$row['C']) : null;
                $fechaRegistro = isset($row['D']) ? trim((string)$row['D']) : null;
                $obs = isset($row['E']) ? trim((string)$row['E']) : null;
                $carteraId = (isset($row['F']) && $row['F'] !== '') ? (int)$row['F'] : null;
                $estado = isset($row['G']) ? trim((string)$row['G']) : null;

                // Reemplazar vacíos o nulos por null reales
                $nroDocumento = in_array($nroDocumento, ['nan', 'None', 'null', '']) ? null : $nroDocumento;
                $telefono = in_array($telefono, ['nan', 'None', 'null', '']) ? null : $telefono;
                $estado = in_array($estado, ['nan', 'None', 'null', '']) ? null : $estado;

                if (!$nroDocumento && !$telefono) continue; // Saltar filas completamente vacías

                // Criterio 5: Verificar duplicados internos (nro_documento + telefono)
                $llaveClave = $nroDocumento . '_' . $telefono;
                if (isset($vistos[$llaveClave])) {
                    $duplicadosExcel++;
                } else {
                    $vistos[$llaveClave] = true;
                }

                // Resumen por estado
                if ($estado) {
                    $resumenEstado[$estado] = ($resumenEstado[$estado] ?? 0) + 1;
                }

                // Guardar los primeros 5 para la previsualización en la tabla web
                if (count($dataPreview) < 5) {
                    $dataPreview[] = [
                        'nro_documento' => $nroDocumento,
                        'telefono' => $telefono,
                        'observaciones' => $observaciones,
                        'fecha_registro' => $fechaRegistro,
                        'obs' => $obs,
                        'cartera_id' => $carteraId,
                        'estado' => $estado
                    ];
                }
            }

            // Criterio 7: Contar registros actuales en SQL Server antes de la carga
            $registrosAntes = DB::connection($this->dbConnection)->table($this->tabla)->count();

            return response()->json([
                'archivo_nombre' => $file->getClientOriginalName(),
                'total_filas_excel' => count($rows) - 1,
                'duplicados_excel' => $duplicadosExcel,
                'registros_antes_sql' => $registrosAntes,
                'resumen_estados' => $resumenEstado,
                'primeras_filas' => $dataPreview,
                'temporal_path' => $file->store('temp_excels') // Guardamos temporalmente para la Fase 2
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al leer Excel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * FASE 2: Carga definitiva (Modo APPEND) cuando el usuario da clic a "Confirmar"
     */
    public function cargar(Request $request)
    {
        $request->validate([
            'temporal_path' => 'required|string'
        ]);

        $tiempoInicio = microtime(true);
        $path = storage_path('app/private/' . $request->temporal_path); // Laravel 11 guarda en app/private por defecto

        if (!file_exists($path)) {
            return response()->json(['error' => 'El archivo temporal expiró o no existe.'], 404);
        }

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            
            $registrosAntes = DB::connection($this->dbConnection)->table($this->tabla)->count();
            $now = Carbon::now()->toDateTimeString();
            
            $insertBuffer = [];
            $chunkSize = 1000; // Criterio 8: chunksize = 1000 de Pandas

            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i];
                
                $nroDocumento = isset($row['A']) ? trim((string)$row['A']) : null;
                $telefono = isset($row['B']) ? trim((string)$row['B']) : null;
                
                $nroDocumento = in_array($nroDocumento, ['nan', 'None', 'null', '']) ? null : $nroDocumento;
                $telefono = in_array($telefono, ['nan', 'None', 'null', '']) ? null : $telefono;

                if (!$nroDocumento && !$telefono) continue;

                // Construimos el array asociativo para el insert masivo
                $insertBuffer[] = [
                    'nro_documento'  => $nroDocumento,
                    'telefono'       => $telefono,
                    'observaciones'  => isset($row['C']) ? trim((string)$row['C']) : null,
                    'fecha_registro' => isset($row['D']) ? trim((string)$row['D']) : null,
                    'obs'            => isset($row['E']) ? trim((string)$row['E']) : null,
                    'cartera_id'     => (isset($row['F']) && $row['F'] !== '') ? (int)$row['F'] : null,
                    'estado'         => isset($row['G']) ? trim((string)$row['G']) : null,
                    'created_at'     => $now // Criterio 4: Agregar fecha de sistema
                ];

                // Si llegamos al límite del chunksize, ejecutamos inserción masiva
                if (count($insertBuffer) === $chunkSize) {
                    DB::connection($this->dbConnection)->table($this->tabla)->insert($insertBuffer);
                    $insertBuffer = [];
                }
            }

            // Insertar cualquier registro restante en el buffer
            if (count($insertBuffer) > 0) {
                DB::connection($this->dbConnection)->table($this->tabla)->insert($insertBuffer);
            }

            // Eliminar archivo temporal de la memoria
            unlink($path);

            // Criterios 9 y 10: Verificaciones finales y Resumen Ejecutivo
            $registrosDespues = DB::connection($this->dbConnection)->table($this->tabla)->count();
            $ultimaFecha = DB::connection($this->dbConnection)->table($this->tabla)->max('created_at');
            $filasInsertadas = $registrosDespues - $registrosAntes;
            $tiempoTotal = round(microtime(true) - $tiempoInicio, 1);

            return response()->json([
                'status' => 'COMPLETADO',
                'total_filas_excel' => count($rows) - 1,
                'registros_antes_sql' => $registrosAntes,
                'filas_insertadas' => $filasInsertadas,
                'registros_despues_sql' => $registrosDespues,
                'ultimo_created' => $ultimaFecha,
                'tiempo_total_segundos' => $tiempoTotal
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al insertar datos en SQL Server: ' . $e->getMessage()], 500);
        }
    }
}