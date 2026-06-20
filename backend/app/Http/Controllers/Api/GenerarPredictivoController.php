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
            $archivo = $request->file('File');
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

            // Leer DNIs del Excel
            $dnis = $this->leerDnisExcel($rutaFinal);
            $totalDnis = count($dnis);

            if ($totalDnis === 0) {
                return response()->json(['warning' => 'No se encontraron DNIs en el Excel'], 200);
            }

            // Buscar teléfonos en SQL Server (chunks de 1000 para IN)
            $chunks = array_chunk($dnis, 1000);
            $telefonos = [];

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $query = "
                    SELECT nro_documento, telefono, unico, cartera_id, operador, 
                            peso_telefono, estado
                    FROM [{$this->tabla}]
                    WHERE nro_documento IN ({$placeholders})
                      AND estado = 'Activo'
                    ORDER BY nro_documento, unico ASC
                ";
                $resultados = DB::connection($this->dbConnection)->select($query, $chunk);
                $telefonos = array_merge($telefonos, $resultados);
            }

            // Agrupar por DNI
            $agrupado = [];
            foreach ($telefonos as $t) {
                $dni = $t->nro_documento;
                if (!isset($agrupado[$dni])) {
                    $agrupado[$dni] = [];
                }
                $agrupado[$dni][] = $t;
            }

            // DNIs no encontrados
            $dnisEncontrados = array_keys($agrupado);
            $dnisNoEncontrados = array_diff($dnis, $dnisEncontrados);

            return response()->json([
                'success' => true,
                'resumen' => [
                    'total_dnis_excel' => $totalDnis,
                    'dnis_con_telefonos' => count($agrupado),
                    'dnis_sin_telefonos' => count($dnisNoEncontrados),
                    'total_telefonos' => count($telefonos)
                ],
                'dnis_no_encontrados' => array_values($dnisNoEncontrados),
                'telefonos_por_dni' => $agrupado,
                'telefonos_planos' => $telefonos
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al procesar DNIs: ' . $e->getMessage()
            ], 500);
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
            $lineas[] = "{$nombrePredictivo}\t{$telefono}\t{$cadena}\t\t9999";
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
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($ruta);
        $worksheet = $spreadsheet->getActiveSheet();

        $dnis = [];
        $rowIndex = 0;

        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $valor = $cell->getValue();
                
                if ($rowIndex === 0) break;

                $dni = trim((string)$valor);
                if ($dni && !in_array($dni, $dnis)) {
                    $dnis[] = $dni;
                }
                break;
            }
            $rowIndex++;
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