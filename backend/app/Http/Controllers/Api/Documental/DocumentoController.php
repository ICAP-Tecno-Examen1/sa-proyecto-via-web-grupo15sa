<?php

namespace App\Http\Controllers\Api\Documental;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Services\Auditoria;
use App\Services\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CU7 · Repositorio documental histórico.
 * Registro, clasificación (tipo, categoría, gestión), búsqueda con filtros,
 * visualización, descarga y archivo. El alcance lo decide DocumentoService.
 */
class DocumentoController extends Controller
{
    public function __construct(private readonly DocumentoService $documentos) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(array_keys(Documento::TIPOS))],
            'categoria' => ['nullable', Rule::in(Documento::CATEGORIAS)],
            'estado' => ['nullable', Rule::in([Documento::VIGENTE, Documento::ARCHIVADO])],
            'gestion' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $usuario = $request->user();

        $pagina = $this->documentos->visibles($usuario)
            ->with(['titulares:id_usuario,nombres,apellidos,ci', 'registrador:id_usuario,nombres,apellidos'])
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['categoria'] ?? null, fn ($q, $v) => $q->where('categoria', $v))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['gestion'] ?? null, fn ($q, $v) => $q->where('gestion', $v))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(COALESCE(descripcion, \'\')) LIKE ?', [$patron])
                    ->orWhereHas('titulares', fn ($t) => $t->where(fn ($x) => $x
                        ->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])->orWhereRaw('LOWER(ci) LIKE ?', [$patron]))));
            })
            ->orderByDesc('gestion')->orderByDesc('id_documento')
            ->paginate($f['por_pagina'] ?? 15);

        return response()->json([
            ...$pagina->toArray(),
            'opciones' => [
                'tipos' => Documento::TIPOS,
                'categorias_consulta' => $this->documentos->categoriasPermitidas($usuario),
                'categorias_registro' => $this->documentos->categoriasParaRegistrar($usuario),
                'gestiones' => $this->documentos->visibles($usuario)->whereNotNull('gestion')->distinct()->orderByDesc('gestion')->pluck('gestion'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $archivos = config('icap.archivos');
        $usuario = $request->user();

        $datos = $request->validate([
            ...$this->reglas($usuario),
            'archivo' => ['required', 'file', 'mimes:'.implode(',', $archivos['extensiones']), 'max:'.$archivos['tamano_maximo_kb']],
            'titulares' => ['nullable', 'array'],
            'titulares.*' => ['integer', Rule::exists('usuario', 'id_usuario')],
        ]);

        $documento = $this->documentos->registrar($datos, $request->file('archivo'), $usuario, $datos['titulares'] ?? []);
        Auditoria::describir("Registró el documento «{$documento->nombre}» en el repositorio");

        return response()->json($documento->load('titulares:id_usuario,nombres,apellidos,ci'), 201);
    }

    public function update(Request $request, Documento $documento): JsonResponse
    {
        $usuario = $request->user();
        $this->autorizarEdicion($request, $documento);
        $archivos = config('icap.archivos');

        $datos = $request->validate([
            ...$this->reglas($usuario),
            'archivo' => ['nullable', 'file', 'mimes:'.implode(',', $archivos['extensiones']), 'max:'.$archivos['tamano_maximo_kb']],
        ]);

        $documento->update(array_intersect_key($datos, array_flip(['nombre', 'descripcion', 'tipo', 'categoria', 'fecha_documento', 'gestion'])));

        if ($request->hasFile('archivo')) {
            $this->documentos->reemplazarArchivo($documento, $request->file('archivo'));
        }

        Auditoria::describir("Actualizó el documento «{$documento->nombre}»");

        return response()->json($documento->load('titulares:id_usuario,nombres,apellidos,ci'));
    }

    /** Pasa el documento al archivo histórico (o lo devuelve a vigente). */
    public function archivar(Request $request, Documento $documento): JsonResponse
    {
        $this->autorizarEdicion($request, $documento);

        $documento->update(['estado' => $documento->estado === Documento::VIGENTE ? Documento::ARCHIVADO : Documento::VIGENTE]);
        Auditoria::describir(($documento->estado === Documento::ARCHIVADO ? 'Archivó' : 'Restituyó')." el documento «{$documento->nombre}»");

        return response()->json($documento);
    }

    public function descargar(Request $request, Documento $documento): StreamedResponse
    {
        abort_unless($this->documentos->puedeVer($request->user(), $documento), 403, 'No tiene acceso a este documento.');

        return $this->documentos->descargar($documento, $request->boolean('ver'));
    }

    // ------------------------------------------------------------------

    private function reglas($usuario): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'tipo' => ['required', Rule::in(array_keys(Documento::TIPOS))],
            'categoria' => ['required', Rule::in($this->documentos->categoriasParaRegistrar($usuario))],
            'fecha_documento' => ['nullable', 'date', 'before_or_equal:today'],
            'gestion' => ['required', 'integer', 'between:1990,'.(now()->year + 1)],
        ];
    }

    /** Edita quien gestiona el repositorio (dentro de lo que puede ver) o quien registró el documento. */
    private function autorizarEdicion(Request $request, Documento $documento): void
    {
        $usuario = $request->user();
        $gestiona = $usuario->tienePermiso('documentos.gestionar') && $this->documentos->puedeVer($usuario, $documento);

        abort_unless($gestiona || $documento->id_usuario_registro === $usuario->id_usuario, 403, 'No puede modificar este documento.');
    }
}
