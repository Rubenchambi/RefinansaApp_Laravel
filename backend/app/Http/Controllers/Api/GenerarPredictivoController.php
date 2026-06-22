<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class GenerarPredictivoController extends Controller
{
    private string $dbConnection = 'Matr_telefonos';
    private string $tabla = 'MatrTelf_Jun26';

    /**
     * =========================================
     * 1. CARGAR EXCEL CON DNIs Y BUSCAR TELÉFONOS
     * =========================================
     */
   public function cargarDnis(Request $request)
    {
        try {
            $archivo = $request->file('File') ?? $request->file('file');
            $rutaExcel = $request->input('ruta_excel');

            $rutaFinal = null;
            if ($archivo) {
                $rutaFinal = $archivo->getRealPath();
            } elseif ($rutaExcel) {
                $rutaFinal = $rutaExcel;
            }

            if (!$rutaFinal || !file_exists($rutaFinal)) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            $dnis = $this->leerDnisExcel($rutaFinal);
            $totalDnis = count($dnis);

            if ($totalDnis === 0) {
                return response()->json(['warning' => 'No se encontraron DNIs en el Excel'], 200);
            }

            // 🎯 Captura de los 12 filtros desde el Frontend (Vue 3)
            $filtroOperador   = $request->input('operador');
            $filtroPeso       = $request->input('peso_telefono');
            $filtroM_Peso     = $request->input('m_peso'); 
            $filtroAño        = $request->input('ano'); 
            $filtroMes        = $request->input('mes'); 
            $filtroUnico      = $request->input('unico'); 
            $filtroCantPredic = $request->input('cant_predic'); 
            $filtroCantGest   = $request->input('cant_gest'); 
            $filtroCarteraId  = $request->input('cartera_id'); 
            $filtroPlanes     = $request->input('planes'); 
            $filtroDetalle    = $request->input('detalle'); 
            $filtroTipo       = $request->input('tipo'); 

            $chunks = array_chunk($dnis, 1000);
            $telefonos = [];

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                
                // 🔍 Extracción estricta de tus columnas reales
                $query = "
                    SELECT 
                        id, nro_documento, telefono, unico, tipo, detalle, 
                        operador, planes, año, mes, m_peso, cartera_id, 
                        peso_telefono, cant_gest, cant_predic, nuevos, rango_monto, 
                        filtrar, cancelo, equivocado, score_telefono, estado
                    FROM [{$this->tabla}]
                    WHERE nro_documento IN ({$placeholders})
                      AND estado = 'Activo'
                ";
                $resultados = DB::connection($this->dbConnection)->select($query, $chunk);
                $telefonos = array_merge($telefonos, $resultados);
            }

            $telefonosFiltrados = [];
            $resumenOperadores  = [];
            $resumenPesos       = [];
            $resumenMontos      = [];

            foreach ($telefonos as $t) {
                
                // ⚡ Bloque de validación de filtros (Match exacto con tu Request)
                if (!empty($filtroOperador) && !in_array(strtolower($t->operador), explode(',', strtolower($filtroOperador)))) continue;
                if (!empty($filtroPeso) && !in_array((string)$t->peso_telefono, explode(',', $filtroPeso))) continue;
                if (!empty($filtroM_Peso) && !in_array((string)$t->m_peso, explode(',', $filtroM_Peso))) continue;
                if (!empty($filtroAño) && !in_array((string)$t->año, explode(',', $filtroAño))) continue;
                if (!empty($filtroMes) && !in_array((string)$t->mes, explode(',', $filtroMes))) continue;
                if (!empty($filtroUnico) && !in_array((string)$t->unico, explode(',', $filtroUnico))) continue;
                if (!empty($filtroCantPredic) && !in_array((string)$t->cant_predic, explode(',', $filtroCantPredic))) continue;
                if (!empty($filtroCantGest) && !in_array((string)$t->cant_gest, explode(',', $filtroCantGest))) continue;
                if (!empty($filtroCarteraId) && !in_array((string)$t->cartera_id, explode(',', $filtroCarteraId))) continue;
                if (!empty($filtroPlanes) && !in_array(strtolower($t->planes), explode(',', strtolower($filtroPlanes)))) continue;
                if (!empty($filtroDetalle) && !in_array(strtolower($t->detalle), explode(',', strtolower($filtroDetalle)))) continue;
                if (!empty($filtroTipo) && !in_array(strtolower($t->tipo), explode(',', strtolower($filtroTipo)))) continue;

                // 📌 Espacio reservado para tus futuros filtros manuales rápidos:
                // if (!empty($filtroFuturo)) { ... }

                $telefonosFiltrados[] = $t;

                // Métricas dinámicas para armar la captura de pantalla del supervisor
                $op = !empty($t->operador) ? strtoupper($t->operador) : 'NO DETERMINADO';
                $resumenOperadores[$op] = ($resumenOperadores[$op] ?? 0) + 1;

                $p_tel = "PESO " . ($t->peso_telefono ?? '0');
                $resumenPesos[$p_tel] = ($resumenPesos[$p_tel] ?? 0) + 1;

                $r_monto = !empty($t->rango_monto) ? strtoupper($t->rango_monto) : 'SIN RANGO';
                $resumenMontos[$r_monto] = ($resumenMontos[$r_monto] ?? 0) + 1;
            }

            // Agrupar por DNI para la distribución final
            $agrupado = [];
            foreach ($telefonosFiltrados as $t) {
                $agrupado[$t->nro_documento][] = $t;
            }

            $dnisEncontrados = array_keys($agrupado);
            $dnisNoEncontrados = array_diff($dnis, $dnisEncontrados);

            return response()->json([
                'success' => true,
                'resumen' => [
                    'total_dnis_excel' => $totalDnis,
                    'dnis_con_telefonos' => count($agrupado),
                    'dnis_sin_telefonos' => count($dnisNoEncontrados),
                    'total_telefonos_brutos' => count($telefonos),
                    'total_telefonos_netos' => count($telefonosFiltrados)
                ],
                'reporte_metrico' => [
                    'por_operador' => $resumenOperadores,
                    'por_peso' => $resumenPesos,
                    'por_rango_monto' => $resumenMontos
                ],
                'dnis_no_encontrados' => array_values($dnisNoEncontrados),
                'telefonos_por_dni' => $agrupado,
                'telefonos_planos' => $telefonosFiltrados // Directo al v-for de la tabla en Vue 3
            ]);

        } catch (Exception $e) {
            return response()->json(['error' => 'Error crítico en procesamiento: ' . $e->getMessage()], 500);
        }
    }

/**
     * =========================================
     * 5. OBTENER OPCIONES PARA LOS SELECTS VUE
     * =========================================
     */
    public function obtenerOpcionesFiltros()
    {
        try {
            $conn = DB::connection($this->dbConnection);

            return response()->json([
                'operador'      => array_column($conn->select("SELECT DISTINCT operador FROM [{$this->tabla}] WHERE operador IS NOT NULL AND operador != ''"), 'operador'),
                'peso_telefono' => array_column($conn->select("SELECT DISTINCT peso_telefono FROM [{$this->tabla}] WHERE peso_telefono IS NOT NULL"), 'peso_telefono'),
                'm_peso'        => array_column($conn->select("SELECT DISTINCT m_peso FROM [{$this->tabla}] WHERE m_peso IS NOT NULL"), 'm_peso'),
                'ano'           => array_column($conn->select("SELECT DISTINCT año FROM [{$this->tabla}] WHERE año IS NOT NULL"), 'año'), // Mapea a 'año' en SQL Server
                'mes'           => array_column($conn->select("SELECT DISTINCT mes FROM [{$this->tabla}] WHERE mes IS NOT NULL"), 'mes'),
                'unico'         => array_column($conn->select("SELECT DISTINCT unico FROM [{$this->tabla}] WHERE unico IS NOT NULL"), 'unico'),
                'cant_predic'   => array_column($conn->select("SELECT DISTINCT cant_predic FROM [{$this->tabla}] WHERE cant_predic IS NOT NULL"), 'cant_predic'),
                'cant_gest'     => array_column($conn->select("SELECT DISTINCT cant_gest FROM [{$this->tabla}] WHERE cant_gest IS NOT NULL"), 'cant_gest'),
                'cartera_id'    => array_column($conn->select("SELECT DISTINCT cartera_id FROM [{$this->tabla}] WHERE cartera_id IS NOT NULL"), 'cartera_id'),
                'planes'        => array_column($conn->select("SELECT DISTINCT planes FROM [{$this->tabla}] WHERE planes IS NOT NULL AND planes != ''"), 'planes'),
                'detalle'       => array_column($conn->select("SELECT DISTINCT detalle FROM [{$this->tabla}] WHERE detalle IS NOT NULL AND detalle != ''"), 'detalle'),
                'tipo'          => array_column($conn->select("SELECT DISTINCT tipo FROM [{$this->tabla}] WHERE tipo IS NOT NULL AND tipo != ''"), 'tipo'),
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cargar catálogos de SQL Server: ' . $e->getMessage()], 500);
        }
    }


    /**
     * =========================================
     * 2. GENERAR ARCHIVO DEVALIX
     * Formato: telefono|idcliente|cartera_id
     * =========================================
     */
    public function generarDevalix(Request $request)
    {
        try {
            $telefonos = $request->input('telefonos', []);

            if (empty($telefonos)) {
                return response()->json(['error' => 'No hay teléfonos para generar'], 400);
            }

            $lineas = [];
            foreach ($telefonos as $t) {
                $telefono = $t['telefono'] ?? $t->telefono ?? '';
                $dni = $t['nro_documento'] ?? $t->nro_documento ?? '';
                $cartera = $t['cartera_id'] ?? $t->cartera_id ?? '';
                $lineas[] = "{$telefono}|{$dni}|{$cartera}";
            }

            $contenido = implode("\r\n", $lineas);
            $nombreArchivo = 'devalix_' . date('Ymd_His') . '.txt';

            return $this->descargarTxt($contenido, $nombreArchivo);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

/**
 * =========================================
 * 3. GENERAR ARCHIVO UNCONTAC
 * Formato: cartera [tab] telefono [tab] cadena [tab] espacio [tab] codigo
 * =========================================
 */
public function generarUncontac(Request $request)
{
    try {
        $telefonos = $request->input('telefonos', []);
        $nombrePredictivo = $request->input('nombre_predictivo', '');

        if (empty($telefonos)) {
            return response()->json(['error' => 'No hay teléfonos para generar'], 400);
        }

        if (empty($nombrePredictivo)) {
            return response()->json(['error' => 'Debe indicar el nombre del predictivo'], 400);
        }

        $lineas = [];
        // Header
        $lineas[] = "cartera\ttelefono\tcadena\tespacio\tcodigo";

        foreach ($telefonos as $t) {
            $t = (array) $t;
            
            $telefono = $t['telefono'] ?? '';
            $dni = $t['nro_documento'] ?? '';
            $carteraId = $t['cartera_id'] ?? '';
            
            // Formato: documento={dni}:cartera={cartera_id}
            $cadena = "documento={$dni}:cartera={$carteraId}";
            
            // espacio vacío, codigo siempre 9999
            $lineas[] = "{$nombrePredictivo}<-\t{$telefono}\t{$cadena}\t\t9999";
        }

        $contenido = implode("\r\n", $lineas);
        $nombreArchivo = 'uncontac_' . date('Ymd_His') . '.txt';

        return $this->descargarTxt($contenido, $nombreArchivo);

    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    /**
     * =========================================
     * 4. GENERAR EXCEL CON RESULTADOS
     * =========================================
     */
    public function generarExcel(Request $request)
    {
        try {
            $telefonos = $request->input('telefonos', []);

            if (empty($telefonos)) {
                return response()->json(['error' => 'No hay teléfonos para generar'], 400);
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['nro_documento', 'telefono', 'unico', 'cartera_id', 'operador', 
                         'peso_telefono', 'estado'];
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            }

            $row = 2;
            foreach ($telefonos as $t) {
                $t = (array) $t;
                $sheet->setCellValueByColumnAndRow(1, $row, $t['nro_documento'] ?? '');
                $sheet->setCellValueByColumnAndRow(2, $row, $t['telefono'] ?? '');
                $sheet->setCellValueByColumnAndRow(3, $row, $t['unico'] ?? '');
                $sheet->setCellValueByColumnAndRow(4, $row, $t['cartera_id'] ?? '');
                $sheet->setCellValueByColumnAndRow(5, $row, $t['operador'] ?? '');
                $sheet->setCellValueByColumnAndRow(7, $row, $t['peso_telefono'] ?? '');
                $sheet->setCellValueByColumnAndRow(8, $row, $t['estado'] ?? '');
                $row++;
            }

            $nombreArchivo = 'predictivo_' . date('Ymd_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
            header('Cache-Control: max-age=0');

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * =========================================
     * HELPERS
     * =========================================
     */
private function leerDnisExcel(string $ruta): array
    {
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true); // 🔥 Crucial: Hace la lectura 10 veces más rápida
        $spreadsheet = $reader->load($ruta);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = $worksheet->getHighestRow(); 
        $dnis = [];

        // Empezamos en la fila 2 para saltar la cabecera de la columna A
        for ($row = 2; $row <= $highestRow; $row++) {
            $valor = $worksheet->getCell("A{$row}")->getValue();
            $dni = trim((string)$valor);

            // Validamos que no esté vacío y no duplicarlo en la lista de búsqueda
            if ($dni !== '' && !in_array($dni, $dnis)) {
                $dnis[] = $dni;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $dnis;
    }

    private function descargarTxt(string $contenido, string $nombreArchivo)
    {
        return response($contenido, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
            'Cache-Control' => 'no-cache'
        ]);
    }
}