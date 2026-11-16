<?php

namespace App\Http\Controllers\Api\Inscripcion;

use App\Http\Controllers\Controller;
use App\Models\OfertaAcademica;
use App\Models\Preinscripcion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\DocumentoService;
use App\Services\MatriculaService;
use App\Services\OfertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU3 · Preinscripción desde la web y su revisión por la Secretaria.
 */
class PreinscripcionController extends Controller
{
    public function __construct(
        private readonly MatriculaService $matriculas,
        private readonly OfertaService $ofertas,
        private readonly DocumentoService $documentos,
    ) {}

    // ------------------------------------------------------------------
    // Estudiante
    // ------------------------------------------------------------------

    public function mias(Request $request): JsonResponse
    {
        $lista = Preinscripcion::where('id_usuario', $request->user()->id_usuario)
            ->with(['oferta' => fn ($q) => $q->with(OfertaService::RELACIONES), 'arancel', 'matricula:id_matricula,id_preinscripcion,numero_registro,estado'])
            ->orderByDesc('id_preinscripcion')->get()
            ->map(fn (Preinscripcion $p) => $this->formato($p));

        return response()->json([
            'preinscripciones' => $lista,
            'documentos_faltantes' => array_values($this->documentos->requisitosFaltantes($request->user())),
        ]);
    }

    /**
     * Crea la preinscripción. El estudiante la crea para sí mismo; la Secretaria
     * puede crearla a nombre de un estudiante indicando id_usuario.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_oferta' => ['required', 'integer', Rule::exists('oferta_academica', 'id_oferta')],
            'id_arancel' => ['nullable', 'integer', Rule::exists('arancel', 'id_arancel')],
            'id_usuario' => ['nullable', 'integer', Rule::exists('usuario', 'id_usuario')],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user();
        $estudiante = $actor;

        if (! empty($datos['id_usuario']) && (int) $datos['id_usuario'] !== $actor->id_usuario) {
            abort_unless($actor->tienePermiso('preinscripciones.revisar'), 403, 'No puede preinscribir a otra persona.');
            $estudiante = Usuario::findOrFail($datos['id_usuario']);
            abort_unless($estudiante->tieneRol(Rol::ESTUDIANTE), 422, 'El usuario seleccionado no tiene el rol Estudiante.');
        } else {
            abort_unless($actor->tienePermiso('preinscripciones.propias'), 403, 'Su rol no puede registrar preinscripciones propias.');
        }

        $pre = $this->matriculas->preinscribir(
            $estudiante,
            OfertaAcademica::findOrFail($datos['id_oferta']),
            $datos['id_arancel'] ?? null,
            $datos['observacion'] ?? null,
        );

        Auditoria::describir("Registró la preinscripción #{$pre->id_preinscripcion} de {$estudiante->nombre_completo}");

        return response()->json($this->formato($pre->load(['oferta' => fn ($q) => $q->with(OfertaService::RELACIONES), 'arancel'])), 201);
    }

    /** El estudiante corrigió lo observado y vuelve a enviar su solicitud. */
    public function reenviar(Request $request, Preinscripcion $preinscripcion): JsonResponse
    {
        abort_unless($preinscripcion->id_usuario === $request->user()->id_usuario, 403, 'Esta preinscripción no le pertenece.');

        $this->matriculas->reenviar($preinscripcion);
        Auditoria::describir("Reenvió a revisión la preinscripción #{$preinscripcion->id_preinscripcion}");

        return response()->json(['message' => 'Preinscripción reenviada a revisión.']);
    }

    // ------------------------------------------------------------------
    // Secretaría
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in([Preinscripcion::PENDIENTE, Preinscripcion::OBSERVADA, Preinscripcion::APROBADA, Preinscripcion::RECHAZADA])],
            'id_oferta' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $pagina = Preinscripcion::with(['usuario', 'oferta' => fn ($q) => $q->with(OfertaService::RELACIONES), 'arancel', 'matricula:id_matricula,id_preinscripcion,numero_registro,estado'])
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($f['id_oferta'] ?? null, fn ($q, $id) => $q->where('id_oferta', $id))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->whereHas('usuario', fn ($u) => $u->where(fn ($w) => $w
                    ->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])->orWhereRaw('LOWER(ci) LIKE ?', [$patron])));
            })
            // Primero lo que espera revisión.
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 WHEN 'OBSERVADA' THEN 1 ELSE 2 END")
            ->orderByDesc('id_preinscripcion')
            ->paginate($f['por_pagina'] ?? 15);

        $pagina->getCollection()->transform(fn (Preinscripcion $p) => $this->formato($p));

        return response()->json($pagina);
    }

    /** Solicitud completa para revisar: datos personales, oferta y expediente. */
    public function show(Preinscripcion $preinscripcion): JsonResponse
    {
        $preinscripcion->load(['usuario', 'oferta' => fn ($q) => $q->with(OfertaService::RELACIONES), 'arancel', 'revisor:id_usuario,nombres,apellidos',
            'matricula:id_matricula,id_preinscripcion,numero_registro,estado']);
        $estudiante = $preinscripcion->usuario;

        return response()->json([
            ...$this->formato($preinscripcion),
            'documentos' => $estudiante->documentos()->orderByDesc('documento.id_documento')->get(),
            'documentos_faltantes' => array_values($this->documentos->requisitosFaltantes($estudiante)),
            'otras_matriculas' => $estudiante->matriculas()->count(),
        ]);
    }

    public function revisar(Request $request, Preinscripcion $preinscripcion): JsonResponse
    {
        $datos = $request->validate([
            'decision' => ['required', Rule::in([Preinscripcion::APROBADA, Preinscripcion::OBSERVADA, Preinscripcion::RECHAZADA])],
            'id_arancel' => ['nullable', 'required_if:decision,'.Preinscripcion::APROBADA, 'integer', Rule::exists('arancel', 'id_arancel')],
            'observacion' => ['nullable', 'required_unless:decision,'.Preinscripcion::APROBADA, 'string', 'min:5', 'max:500'],
        ], [
            'id_arancel.required_if' => 'Debe seleccionar el plan de pago para generar la matrícula.',
            'observacion.required_unless' => 'Debe explicar el motivo de la observación o del rechazo.',
        ]);

        if ($datos['decision'] === Preinscripcion::APROBADA) {
            $matricula = $this->matriculas->aprobar($preinscripcion, (int) $datos['id_arancel'], $request->user(), $datos['observacion'] ?? null);
            Auditoria::describir("Aprobó la preinscripción #{$preinscripcion->id_preinscripcion} y generó la matrícula {$matricula->numero_registro}");

            return response()->json([
                'message' => "Matrícula {$matricula->numero_registro} generada, pendiente de pago.",
                'matricula' => $matricula->only(['id_matricula', 'numero_registro', 'estado']),
            ]);
        }

        $this->matriculas->observarORechazar($preinscripcion, $datos['decision'], $datos['observacion'], $request->user());
        Auditoria::describir("Marcó la preinscripción #{$preinscripcion->id_preinscripcion} como {$datos['decision']}");

        return response()->json(['message' => 'Preinscripción actualizada.']);
    }

    private function formato(Preinscripcion $p): array
    {
        return [
            ...$p->only(['id_preinscripcion', 'estado', 'observacion', 'id_oferta', 'id_arancel', 'id_usuario']),
            'fecha' => $p->fecha?->format('Y-m-d'),
            'fecha_revision' => $p->fecha_revision?->format('Y-m-d H:i:s'),
            'oferta' => $this->ofertas->resumen($p->oferta),
            'plan_preferido' => $p->arancel?->only(['id_arancel', 'concepto', 'monto', 'tipo_cobro', 'numero_cuotas']),
            'estudiante' => $p->relationLoaded('usuario') ? $p->usuario : null,
            'revisor' => $p->relationLoaded('revisor') ? $p->revisor?->nombre_completo : null,
            'matricula' => $p->relationLoaded('matricula') ? $p->matricula : null,
        ];
    }
}
