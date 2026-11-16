<?php

namespace App\Http\Controllers\Api\Inscripcion;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU3 · Expediente único del estudiante.
 *
 * Los documentos personales se entregan una sola vez: quedan asociados al
 * usuario (expediente_documento) y sirven para todas sus inscripciones.
 */
class ExpedienteController extends Controller
{
    /** Tipos de documento que forman parte del expediente personal. */
    private const TIPOS_PERSONALES = ['CI', 'TITULO', 'FOTOGRAFIA', 'CERTIFICADO_NACIMIENTO', 'OTRO'];

    public function __construct(private readonly DocumentoService $documentos) {}

    public function propio(Request $request): JsonResponse
    {
        return response()->json($this->expediente($request->user()));
    }

    public function subirPropio(Request $request): JsonResponse
    {
        return $this->guardar($request, $request->user());
    }

    /** El estudiante puede retirar un documento personal que él mismo subió. */
    public function eliminarPropio(Request $request, Documento $documento): JsonResponse
    {
        $usuario = $request->user();

        abort_unless(
            $documento->id_usuario_registro === $usuario->id_usuario && $documento->categoria === 'PERSONAL',
            403,
            'Solo puede eliminar documentos personales que usted mismo subió.'
        );

        abort_if(
            $usuario->matriculas()->where('estado', '<>', 'ANULADA')->exists(),
            422,
            'Ya tiene una matrícula: sus documentos forman parte del expediente y no pueden eliminarse. Suba una nueva versión si necesita actualizarlos.'
        );

        $this->documentos->eliminar($documento);
        Auditoria::describir("Eliminó de su expediente el documento «{$documento->nombre}»");

        return response()->json($this->expediente($usuario));
    }

    /** Secretaría: expediente de cualquier estudiante. */
    public function show(Usuario $usuario): JsonResponse
    {
        return response()->json($this->expediente($usuario));
    }

    public function subir(Request $request, Usuario $usuario): JsonResponse
    {
        return $this->guardar($request, $usuario);
    }

    // ------------------------------------------------------------------

    private function guardar(Request $request, Usuario $titular): JsonResponse
    {
        $archivos = config('icap.archivos');

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(self::TIPOS_PERSONALES)],
            'nombre' => ['nullable', 'string', 'min:3', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'archivo' => ['required', 'file', 'mimes:'.implode(',', $archivos['extensiones']), 'max:'.$archivos['tamano_maximo_kb']],
        ]);

        $documento = $this->documentos->registrar([
            'nombre' => $datos['nombre'] ?? Documento::TIPOS[$datos['tipo']].' - '.$titular->nombre_completo,
            'descripcion' => $datos['descripcion'] ?? null,
            'tipo' => $datos['tipo'],
            'categoria' => 'PERSONAL',
        ], $request->file('archivo'), $request->user(), [$titular->id_usuario]);

        Auditoria::describir("Agregó «{$documento->nombre}» al expediente de {$titular->nombre_completo}");

        return response()->json($this->expediente($titular), 201);
    }

    private function expediente(Usuario $usuario): array
    {
        $faltantes = $this->documentos->requisitosFaltantes($usuario);

        return [
            'titular' => $usuario->only(['id_usuario', 'nombre_completo', 'ci', 'correo', 'telefono']),
            'documentos' => $usuario->documentos()->orderByDesc('documento.id_documento')->get()->makeHidden('pivot'),
            'requeridos' => collect(config('icap.documentos_requeridos'))->map(fn ($etiqueta, $tipo) => [
                'tipo' => $tipo, 'etiqueta' => $etiqueta, 'presentado' => ! isset($faltantes[$tipo]),
            ])->values(),
            'tipos' => collect(self::TIPOS_PERSONALES)->map(fn ($tipo) => ['tipo' => $tipo, 'etiqueta' => Documento::TIPOS[$tipo]]),
            'completo' => empty($faltantes),
        ];
    }
}
