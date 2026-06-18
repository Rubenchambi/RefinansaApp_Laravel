<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class ActualizarRequerimientosController extends Controller
{
    // =========================================
    // CONFIGURACIÓN
    // =========================================

    private string $tabla = 'Actualizar_datos';
    private string $dbConnection = 'actualizacion';

    // Tipos de datos (equivalente a dtype de pandas)
    private array $tiposDatos = [
        'DNI' => 'string',
        'ENTIDADES' => 'string',
        'CALIFICACION_SBS' => 'string',
        'RANGO_SUELDO' => 'string',
        'RANGO_EDAD' => 'string',
        'NOMBRE_UGEL' => 'string',
        'TRAMO_FACT' => 'string',
        'PAGOS' => 'float',
        'FECHA_PAGOS' => 'string'
    ];

        public function monitor(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $perPage = $request->query('per_page', 15);

            $query = DB::connection($this->dbConnection)->table($this->tabla);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('DNI', 'LIKE', "%{$search}%")
                      ->orWhere('ENTIDADES', 'LIKE', "%{$search}%")
                      ->orWhere('CALIFICACION_SBS', 'LIKE', "%{$search}%");
                });
            }

            $totalRegistros = DB::connection($this->dbConnection)->table($this->tabla)->count();
            $activos = DB::connection($this->dbConnection)->table($this->tabla)
                ->sum('PAGOS') ?? 0;
            
            $otros = DB::connection($this->dbConnection)
                ->table($this->tabla)
                ->distinct()
                ->count('DNI');

            $registros = $query->paginate($perPage);

            return response()->json([
                'kpis' => [
                    'total' => $totalRegistros,
                    'activos' => round((float)$activos, 2),
                    'otros' => $otros
                ],
                'registros' => $registros
            ]);

        } catch (Exception $e) {
              Log::error('MONITOR ERROR: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al leer monitor SQL: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * =========================================
     * PROCESO CARGA EXCEL → SQL SERVER
     * =========================================
     */
    public function procesarCarga(Request $request)
    {
        $inicio = microtime(true);

        // =========================================
        // OBTENER PARÁMETROS DINÁMICOS
        // =========================================
        $rutaExcel = $request->input('ruta_excel');
        $archivoSubido = $request->file('archivo');
        $mesAsignacion = (int) $request->input('mes_asignacion');
        $anioAsignacion = (int) $request->input('anio_asignacion');
        $confirmar = strtoupper($request->input('confirmar', 'N'));
        $opcion = $request->input('opcion', '4');

        // =========================================
        // DETERMINAR RUTA DEL ARCHIVO
        // =========================================
        $rutaFinal = null;

        if ($archivoSubido) {
            // Archivo subido vía form-data
            $rutaFinal = $archivoSubido->getRealPath();
        } elseif ($rutaExcel) {
            // Ruta directa (UNC o local)
            $rutaFinal = $rutaExcel;
        }

        if (!$rutaFinal) {
            return response()->json([
                'error' => 'Debes proporcionar una ruta_excel o subir un archivo.'
            ], 400);
        }

        // =========================================
        // VALIDAR ARCHIVO
        // =========================================
        if (!file_exists($rutaFinal)) {
            return response()->json([
                'error' => 'No se encontró el archivo Excel.',
                'ruta' => $rutaFinal
            ], 404);
        }

        try {
            // =========================================
            // LEER EXCEL (equivalente a pd.read_excel)
            // =========================================
            $datos = $this->leerExcel($rutaFinal);
            $totalRegistros = count($datos);

            if ($totalRegistros === 0) {
                return response()->json([
                    'warning' => 'El archivo Excel no contiene registros.'
                ], 200);
            }

            // =========================================
            // CONFIRMAR EJECUCIÓN
            // =========================================
            if ($confirmar !== 'S') {
                return response()->json([
                    'message' => 'Proceso cancelado. Envía confirmar=S para ejecutar.',
                    'registros_encontrados' => $totalRegistros,
                    'preview' => array_slice($datos, 0, 5)
                ], 200);
            }

            // =========================================
            // CONEXIÓN SQL (usa tu config/database.php existente)
            // =========================================
            try {
                DB::connection($this->dbConnection)->getPdo();
            } catch (Exception $e) {
                return response()->json([
                    'error' => 'Error de conexión a SQL Server: ' . $e->getMessage()
                ], 500);
            }

            // =========================================
            // CARGA DE DATOS
            // =========================================
           DB::connection($this->dbConnection)->statement("DELETE FROM [{$this->tabla}]");


            // Insert en chunks de 5000 (equivalente a chunksize=5000 de pandas)
            $chunks = array_chunk($datos, 5000);
            $totalInsertados = 0;

            foreach ($chunks as $chunk) {
                DB::connection($this->dbConnection)->table($this->tabla)->insert($chunk);
                $totalInsertados += count($chunk);
            }

            // =========================================
            // OPCIÓN DE ACTUALIZACIÓN
            // =========================================
            $resultadoActualizacion = null;

            switch ($opcion) {
                case '1':
                    $resultadoActualizacion = $this->actualizarAdministrada($mesAsignacion, $anioAsignacion);
                    break;

                case '2':
                    $resultadoActualizacion = $this->actualizarHipotecario($mesAsignacion, $anioAsignacion);
                    break;

                case '3':
                    $resultadoActualizacion = $this->actualizarConvenio($mesAsignacion, $anioAsignacion);
                    break;

                case '4':
                    $resultadoActualizacion = ['message' => 'No se ejecutó ninguna actualización.'];
                    break;

                default:
                    $resultadoActualizacion = ['warning' => 'Opción inválida.'];
                    break;
            }

            // =========================================
            // RESUMEN DEL PROCESO
            // =========================================
            $fin = microtime(true);
            $tiempo = round($fin - $inicio, 2);

            return response()->json([
                'success' => true,
                'resumen' => [
                    'archivo_origen' => $rutaFinal,
                    'tabla_destino' => $this->tabla,
                    'registros' => $totalRegistros,
                    'registros_insertados' => $totalInsertados,
                    'mes_asignacion' => $mesAsignacion,
                    'anio_asignacion' => $anioAsignacion,
                    'tiempo_segundos' => $tiempo,
                ],
                'actualizacion' => $resultadoActualizacion
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al leer monitor SQL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * =========================================
     * LEER EXCEL CON TIPOS DE DATOS
     * Equivalente a pd.read_excel con dtype
     * =========================================
     */
    private function leerExcel(string $ruta): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($ruta);
        
        $calculationEngine = $spreadsheet->getCalculationEngine();
        $calculationEngine->disableCalculationCache();
        $calculationEngine->flushInstance();
        
        $worksheet = $spreadsheet->getActiveSheet();

        $datos = [];
        $headers = [];
        $rowIndex = 0;
        
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            $fila = [];
            $colIndex = 0;
            
            foreach ($cellIterator as $cell) {
                $valor = $cell->getCalculatedValue();

                if (is_string($valor) && str_starts_with($valor, '=')) {
                    $valor = $cell->getValue();
                }

                if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $valor = $valor->getPlainText();
                }

                if ($rowIndex === 0) {
                    // 🧹 Limpiar header
                    $headerLimpio = trim((string)$valor);
                    $headerLimpio = preg_replace('/\s+/', ' ', $headerLimpio);
                    $headers[] = $headerLimpio;
                } else {
                    $header = $headers[$colIndex] ?? null;
                    
                    // 🚫 IGNORAR columnas sin nombre
                    if (empty($header)) {
                        $colIndex++;
                        continue;
                    }
                    
                    if ($header && isset($this->tiposDatos[$header])) {
                        $tipo = $this->tiposDatos[$header];
                        $fila[$header] = $this->castValor($valor, $tipo);
                    } else {
                        $fila[$header] = $valor;
                    }
                }
                $colIndex++;
            }
            
            if ($rowIndex > 0 && !empty($fila)) {
                $datos[] = $fila;
            }
            
            $rowIndex++;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $datos;
    }

    /**
     * =========================================
     * CAST DE VALORES SEGÚN TIPO
     * Equivalente al dtype de pandas
     * =========================================
     */
    private function castValor($valor, string $tipo)
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        switch ($tipo) {
            case 'string':
                return (string) $valor;

            case 'float':
                $valorLimpio = str_replace(',', '', (string)$valor);
                return is_numeric($valorLimpio) ? (float) $valorLimpio : null;

            case 'int':
                return (int) $valor;

            case 'bool':
                return (bool) $valor;

            default:
                return $valor;
        }
    }

    /**
     * =========================================
     * ACTUALIZACIÓN ADMINISTRADA
     * =========================================
     */
    private function actualizarAdministrada(int $mes, int $anio): array
    {
        $sql = "
            UPDATE matr_hip_2025
            SET pagos = ad.PAGOS,
                fecha_pago = ad.FECHA_PAGOS
            FROM matr_hip_2025 AS pre
            JOIN Actualizar_datos AS ad
                ON ad.ENTIDADES = pre.idcliente
            WHERE mes_asignacion = :mes
              AND año_asignacion = :anio
              AND cartera = 'ADMINISTRADA'
              AND estado = 'Activo'
        ";

        $afectados = DB::connection($this->dbConnection)->update($sql, [
            'mes' => $mes,
            'anio' => $anio
        ]);

        return [
            'tipo' => 'ADMINISTRADA',
            'filas_afectadas' => $afectados,
            'message' => 'Actualización ADMINISTRADA completada.'
        ];
    }

    /**
     * =========================================
     * ACTUALIZACIÓN HIPOTECARIO
     * =========================================
     */
    private function actualizarHipotecario(int $mes, int $anio): array
    {
        $sql = "
            UPDATE matr_hip_2025
            SET pagos =
                CASE
                    WHEN p.PAGOS >= (m.total_saldo_vencido * m.total_saldo_diferido)
                        THEN (m.total_saldo_vencido * m.total_saldo_diferido)
                    ELSE p.PAGOS
                END,
                fecha_pago = CAST(p.FECHA_PAGOS AS VARCHAR(20))
            FROM matr_hip_2025 AS m
            JOIN Actualizar_datos AS p
                ON p.ENTIDADES = m.idcliente
            WHERE m.cartera = 'HIPOTECARIO'
              AND m.mes_asignacion = :mes
              AND m.año_asignacion = :anio
              AND m.estado = 'Activo'
        ";

        $afectados = DB::connection($this->dbConnection)->update($sql, [
            'mes' => $mes,
            'anio' => $anio
        ]);

        return [
            'tipo' => 'HIPOTECARIO',
            'filas_afectadas' => $afectados,
            'message' => 'Actualización HIPOTECARIO completada.'
        ];
    }

    /**
     * =========================================
     * ACTUALIZACIÓN CONVENIO
     * =========================================
     */
    private function actualizarConvenio(int $mes, int $anio): array
    {
        $sql = "
            UPDATE matr_conv_2025
            SET pagos = ad.PAGOS,
                fecha_pago = ad.FECHA_PAGOS
            FROM matr_conv_2025 AS pre
            JOIN Actualizar_datos AS ad
                ON ad.ENTIDADES = pre.idcliente
            WHERE mes_asignacion = :mes
              AND año_asignacion = :anio
              AND cartera = 'CONVENIO'
              AND estado = 'Activo'
        ";

        $afectados = DB::connection($this->dbConnection)->update($sql, [
            'mes' => $mes,
            'anio' => $anio
        ]);

        return [
            'tipo' => 'CONVENIO',
            'filas_afectadas' => $afectados,
            'message' => 'Actualización CONVENIO completada.'
        ];
    }
}