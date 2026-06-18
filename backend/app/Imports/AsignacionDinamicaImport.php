<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // <-- Clave!
use Illuminate\Support\Facades\DB;

class AsignacionDinamicaImport implements ToCollection, WithHeadingRow
{
    protected $tabla;

    public function __construct($tabla) {
        $this->tabla = $tabla;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $insertData = [];
            foreach ($row as $key => $value) {
                if (empty($key)) continue;
                // Aplicamos la misma limpieza de columnas que en el controlador
                $columnaLimpia = strtolower(preg_replace('/[^A-Za-z0-9_]/', '_', trim($key)));
                $insertData[$columnaLimpia] = $value !== null ? (string)$value : null;
            }

            if (!empty($insertData)) {
                DB::connection('asignaciones_origen')->table($this->tabla)->insert($insertData);
            }
        }
    }
}