<?php

namespace App\Http\Controllers\Api\Reportes;

use App\Http\Controllers\Controller;
use App\Services\ReporteService;
use App\Support\LibroExcel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CU8 · Reportes con filtros, impresión y exportación (Excel .xlsx, CSV y PDF).
 */
class ReporteController extends Controller
{
    public function __construct(private readonly ReporteService $reportes) {}

    /** Reportes disponibles para el rol activo y opciones de filtro. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->reportes->catalogo($request->user()));
    }

    public function show(Request $request, string $clave): Response
    {
        $filtros = $request->validate([
            'gestion' => ['nullable', 'integer'],
            'id_programa' => ['nullable', 'integer'],
            'id_oferta' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'formato' => ['nullable', 'in:json,xlsx,csv,pdf'],
        ]);

        $reporte = $this->reportes->generar($clave, $filtros, $request->user());
        $archivo = 'reporte-'.str_replace('_', '-', $clave).'-'.now()->format('Ymd-His');

        return match ($filtros['formato'] ?? 'json') {
            'xlsx' => $this->excel($reporte, $archivo),
            'csv' => $this->csv($reporte, $archivo),
            'pdf' => Pdf::loadView('pdf.reporte', [
                'reporte' => $reporte,
                'institucion' => config('icap.institucion'),
                'usuario' => $request->user()->nombre_completo,
            ])->setPaper('a4', count($reporte['columnas']) > 6 ? 'landscape' : 'portrait')->download($archivo.'.pdf'),
            default => response()->json($reporte),
        };
    }

    /** Excel (.xlsx): una columna por dato, sin depender del separador de listas de Windows. */
    private function excel(array $reporte, string $archivo): Response
    {
        $subtitulos = ['Generado: '.$reporte['generado']];

        foreach ($reporte['filtros_aplicados'] as $etiqueta => $valor) {
            $subtitulos[] = "{$etiqueta}: {$valor}";
        }

        return response(LibroExcel::generar($reporte['titulo'], $reporte['columnas'], $reporte['filas'], $subtitulos), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'.xlsx"',
        ]);
    }

    /** CSV con BOM y punto y coma (para otros programas; en Excel conviene el .xlsx). */
    private function csv(array $reporte, string $archivo): Response
    {
        $salida = fopen('php://temp', 'r+');
        fputcsv($salida, array_column($reporte['columnas'], 'titulo'), ';');

        foreach ($reporte['filas'] as $fila) {
            fputcsv($salida, array_map(function (array $columna) use ($fila) {
                $valor = $fila[$columna['clave']] ?? '';

                return in_array($columna['tipo'], ['moneda', 'porcentaje'], true) && $valor !== ''
                    ? number_format((float) $valor, 2, ',', '')
                    : $valor;
            }, $reporte['columnas']), ';');
        }

        rewind($salida);
        $contenido = "\xEF\xBB\xBF".stream_get_contents($salida);
        fclose($salida);

        return response($contenido, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'.csv"',
        ]);
    }
}
