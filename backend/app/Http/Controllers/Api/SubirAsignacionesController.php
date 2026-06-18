<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use App\Imports\AsignacionDinamicaImport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // <-- Añadido para habilitar cabeceras legibles

// 💡 Mini helper para forzar la lectura con cabeceras reales en el ToArray sin alterar tu código
class ArrancadorCabeceras implements WithHeadingRow {}

class SubirAsignacionesController extends Controller
{
    /**
     * 🖥️ MONITOR EN VIVO: Carga los KPIs y registros de la tabla dinámica actual
     */
    public function monitorAsignacion(Request $request)
    {
        // 1. Resolver el nombre de la tabla de la misma forma que la carga
        $nombreTabla = $this->resolverNombreTabla($request);
        $connection = Schema::connection('asignaciones_origen');

        // Si la tabla aún no existe en SQL Server, retornamos un estado limpio
        if (!$connection->hasTable($nombreTabla)) {
            return response()->json([
                'tabla_existe' => false,
                'nombre_tabla' => $nombreTabla,
                'kpis' => ['total' => 0, 'columnas' => 0],
                'registros' => ['data' => [], 'last_page' => 1]
            ]);
        }

        // 2. Obtener KPIs básicos de la tabla dinámica
        $totalRegistros = DB::connection('asignaciones_origen')->table($nombreTabla)->count();
        $columnas = $connection->getColumnListing($nombreTabla);

        // 3. Obtener registros paginados y aplicar buscador (si se envía un término)
        $search = $request->input('search');
        $query = DB::connection('asignaciones_origen')->table($nombreTabla);

        if (!empty($search) && count($columnas) > 0) {
            $query->where(function($q) use ($columnas, $search) {
                // Buscador inteligente dinámico: busca el término en las primeras 3 columnas (ej: DNI, Cuenta, Operacion)
                $columnasBusqueda = array_slice($columnas, 0, 3);
                foreach ($columnasBusqueda as $idx => $col) {
                    if ($idx === 0) {
                        $q->where($col, 'LIKE', "%{$search}%");
                    } else {
                        $q->orWhere($col, 'LIKE', "%{$search}%");
                    }
                }
            });
        }

        // Paginado de 10 en 10 idéntico al monitor anterior
        $paginado = $query->paginate(10);

        return response()->json([
            'tabla_existe' => true,
            'nombre_tabla' => $nombreTabla,
            'kpis' => [
                'total' => $totalRegistros,
                'columnas' => count($columnas)
            ],
            'registros' => [
                'data' => $paginado->items(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'total' => $paginado->total()
            ]
        ]);
    }

    /**
     * 🚀 PROCESO PRINCIPAL: Previsualización e Importación Masiva
     */
    public function subirAsignacion(Request $request)
    {
        $inicio = microtime(true);

        $archivo = $request->file('archivo');
        $rutaExcelManual = $request->input('ruta_excel_manual');

        if ($archivo) {
            $pathTarget = $archivo->getRealPath();
        } elseif ($rutaExcelManual && file_exists($rutaExcelManual)) {
            $pathTarget = $rutaExcelManual;
        } else {
            return response()->json(['error' => 'No se cargó un archivo válido o la ruta UNC no existe.'], 400);
        }

        $nombreTabla = $this->resolverNombreTabla($request);

        // Configuración de campos String para no perder ceros
        $columnasStringInput = $request->input('columnas_string');
        $columnasString = [];
        if (!empty($columnasStringInput)) {
            $columnasString = array_map('trim', explode(',', str_replace("\n", ",", $columnasStringInput)));
            $columnasString = array_filter($columnasString);
        }

        try {
            // 🛠️ FIX AQUÍ: Cambiamos new \stdClass por nuestro inicializador para obtener las llaves reales de tu Excel
            $reader = Excel::toArray(new ArrancadorCabeceras, $pathTarget);
            if (empty($reader) || empty($reader[0])) {
                return response()->json(['error' => 'El archivo Excel está vacío.'], 400);
            }

            $cabeceras = array_keys($reader[0][0]);
            $totalRegistros = count($reader[0]);

            // MODO PREVIEW ('N') -> Retorna cabeceras y muestra de 10 filas
            if (strtoupper($request->input('confirmar', 'N')) !== 'S') {
                $previewData = array_slice($reader[0], 0, 10);
                $fin = microtime(true);
                return response()->json([
                    'status' => 'preview',
                    'nombre_tabla_calculado' => $nombreTabla,
                    'registros_encontrados' => $totalRegistros,
                    'columnas' => $cabeceras,
                    'preview' => $previewData,
                    'tiempo_segundos' => round($fin - $inicio, 2)
                ]);
            }

            // MODO EJECUCIÓN REAL ('S')
            $dbConnection = Schema::connection('asignaciones_origen');
            $dbConnection->dropIfExists($nombreTabla);
            
            $dbConnection->create($nombreTabla, function (Blueprint $table) use ($cabeceras, $columnasString) {
                // Agregar ID autoincremental automático para control interno
                $table->id();

                foreach ($cabeceras as $columna) {
                    if (empty($columna)) continue;

                    // Sanitizamos el nombre de la columna para SQL Server (quitar espacios o caracteres raros)
                    $columnaLimpia = strtolower(preg_replace('/[^A-Za-z0-9_]/', '_', trim($columna)));

                    if (in_array($columna, $columnasString)) {
                        $table->string($columnaLimpia, 255)->nullable(); 
                    } else {
                        $table->text($columnaLimpia)->nullable(); 
                    }
                }
            });

            Excel::import(new AsignacionDinamicaImport($nombreTabla), $pathTarget);

            $fin = microtime(true);
            return response()->json([
                'status' => 'success',
                'resumen' => [
                    'registros' => $totalRegistros,
                    'registros_insertados' => $totalRegistros,
                    'nombre_tabla' => $nombreTabla,
                    'tiempo_segundos' => round($fin - $inicio, 2),
                    'columnas_creadas' => count($cabeceras)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en procesamiento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 🛠️ Helper privado optimizado y sanitizado para SQL Server
     */
    private function resolverNombreTabla(Request $request)
    {
        // Recuperamos el input limpiando espacios laterales
        $nombreTablaManual = trim($request->input('nombre_tabla_personalizado', ''));

        // Validamos que NO esté vacío y que NO sea el texto literal "null" o "undefined"
        if (!empty($nombreTablaManual) && strtolower($nombreTablaManual) !== 'null' && strtolower($nombreTablaManual) !== 'undefined') {
            // Sanitizamos: dejamos solo letras, números y guiones bajos
            $sanitizada = preg_replace('/[^A-Za-z0-9_]/', '', $nombreTablaManual);
            return $sanitizada ?: "Asignacion_Temporal_Manual";
        }

        // Si viene vacío, armamos el nombre por defecto con los combos
        $cartera = preg_replace('/[^A-Za-z0-9]/', '', trim($request->input('cartera', '')));
        $mes = preg_replace('/[^A-Za-z0-9]/', '', trim($request->input('mes_abreviado', '')));
        $anio = preg_replace('/[^A-Za-z0-9]/', '', trim($request->input('anio_dos_digitos', '')));

        if (!empty($cartera) && !empty($mes) && !empty($anio)) {
            return "Asignacion{$cartera}_{$mes}{$anio}";
        }

        return "Asignacion_Temporal_Carga";
    }
}