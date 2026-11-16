<?php

namespace App\Http\Controllers\Api\Certificacion;

use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\ValidacionRequisito;
use App\Services\Auditoria;
use App\Services\CertificacionService;
use App\Services\DocumentoService;
use App\Services\MatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CU6 · Validación de requisitos y emisión de certificados digitales.
 */
class CertificacionController extends Controller
{
    public function __construct(
        private readonly CertificacionService $certificacion,
        private readonly MatriculaService $matriculas,
    ) {}

    /** Estudiantes que finalizaron, con el estado de sus tres validaciones. */
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'por_pagina' => ['nullable', 'integer', 'between:5,100']]);

        $pagina = Matricula::where('estado', Matricula::FINALIZADA)
            ->with([...MatriculaService::RELACIONES, 'validaciones', 'certificados'])
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(numero_registro) LIKE ?', [$patron])
                    ->orWhereHas('usuario', fn ($u) => $u->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])));
            })
            ->orderByDesc('fecha_finalizacion')->orderByDesc('id_matricula')
            ->paginate($f['por_pagina'] ?? 15);

        $pagina->getCollection()->transform(fn (Matricula $m) => [
            ...$this->matriculas->resumen($m),
            'validaciones' => $m->validaciones->pluck('estado', 'tipo'),
            'certificado' => $m->certificados->firstWhere('estado', Certificado::EMITIDO)?->only(['id_certificado', 'codigo_unico', 'fecha_emision']),
        ]);

        return response()->json($pagina);
    }

    /** Detalle para validar: verificación automática y decisión registrada por requisito. */
    public function show(Request $request, Matricula $matricula): JsonResponse
    {
        abort_unless($matricula->estado === Matricula::FINALIZADA, 422, 'El estudiante aún no finalizó su programa.');

        $matricula->load([...MatriculaService::RELACIONES, 'validaciones.validador:id_usuario,nombres,apellidos']);
        $verificaciones = $this->certificacion->verificaciones($matricula);
        $usuario = $request->user();

        $requisitos = collect(ValidacionRequisito::TIPOS)->map(function (string $tipo) use ($matricula, $verificaciones, $usuario) {
            $validacion = $matricula->validaciones->firstWhere('tipo', $tipo);

            return [
                'tipo' => $tipo,
                'cumple' => $verificaciones[$tipo]['cumple'],
                'detalle' => $verificaciones[$tipo]['detalle'],
                'estado' => $validacion?->estado ?? ValidacionRequisito::PENDIENTE,
                'observacion' => $validacion?->observacion,
                'validador' => $validacion?->validador?->nombre_completo,
                'fecha_validacion' => $validacion?->fecha_validacion?->format('Y-m-d H:i:s'),
                'puede_validar' => $usuario->tienePermiso(CertificacionService::PERMISOS[$tipo]),
            ];
        });

        $certificado = $matricula->certificados()->where('estado', Certificado::EMITIDO)->first();

        return response()->json([
            ...$this->matriculas->resumen($matricula),
            'requisitos' => $requisitos,
            'certificado' => $certificado ? $this->certificacion->datos($certificado) + ['id_certificado' => $certificado->id_certificado] : null,
            'listo_para_emitir' => ! $certificado && $requisitos->every(fn ($r) => $r['estado'] === ValidacionRequisito::APROBADO),
            'puede_emitir' => $usuario->tienePermiso('certificados.autorizar'),
        ]);
    }

    public function validar(Request $request, Matricula $matricula): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(ValidacionRequisito::TIPOS)],
            'estado' => ['required', Rule::in([ValidacionRequisito::APROBADO, ValidacionRequisito::OBSERVADO])],
            'observacion' => ['nullable', 'required_if:estado,'.ValidacionRequisito::OBSERVADO, 'string', 'min:5', 'max:500'],
        ], ['observacion.required_if' => 'Debe indicar qué falta para cumplir el requisito.']);

        abort_unless(
            $request->user()->tienePermiso(CertificacionService::PERMISOS[$datos['tipo']]),
            403,
            'Su rol no valida este tipo de requisito.'
        );

        $this->certificacion->validar($matricula, $datos['tipo'], $datos['estado'], $datos['observacion'] ?? null, $request->user());
        Auditoria::describir("Validó el requisito {$datos['tipo']} de {$matricula->numero_registro} como {$datos['estado']}");

        return $this->show($request, $matricula);
    }

    /** Director: autoriza y emite el certificado. */
    public function emitir(Request $request, Matricula $matricula): JsonResponse
    {
        $certificado = $this->certificacion->emitir($matricula, $request->user());
        Auditoria::describir("Autorizó y emitió el certificado {$certificado->codigo_unico}");

        return $this->show($request, $matricula);
    }

    public function anular(Certificado $certificado): JsonResponse
    {
        $this->certificacion->anular($certificado);
        Auditoria::describir("Anuló el certificado {$certificado->codigo_unico}");

        return response()->json(['message' => 'Certificado anulado.']);
    }

    /** Certificados emitidos (personal). */
    public function certificados(Request $request): JsonResponse
    {
        return response()->json($this->lista(Certificado::query()));
    }

    /** Certificados del propio estudiante. */
    public function mios(Request $request): JsonResponse
    {
        return response()->json($this->lista(
            Certificado::whereHas('matricula', fn ($q) => $q->where('id_usuario', $request->user()->id_usuario))
        ));
    }

    public function pdf(Request $request, Certificado $certificado, DocumentoService $documentos): StreamedResponse
    {
        $usuario = $request->user();
        $propio = $certificado->matricula->id_usuario === $usuario->id_usuario;

        abort_unless($propio || $usuario->tienePermiso('certificacion.ver'), 403, 'No puede descargar este certificado.');
        abort_if($propio && ! $usuario->tienePermiso('certificacion.ver') && $certificado->estado !== Certificado::EMITIDO, 422, 'El certificado fue anulado.');

        return $documentos->entregar($this->certificacion->rutaPdf($certificado), 'certificado-'.$certificado->codigo_unico, true);
    }

    /** Verificación pública de autenticidad (sin sesión): por QR o por código. */
    public function verificar(string $codigo): JsonResponse
    {
        $certificado = Certificado::whereRaw('UPPER(codigo_unico) = ?', [mb_strtoupper(trim($codigo))])->first();

        if (! $certificado) {
            return response()->json(['valido' => false, 'message' => 'No existe ningún certificado con ese código.'], 404);
        }

        // El documento de identidad se muestra parcialmente: la consulta es pública.
        $datos = $this->certificacion->datos($certificado);
        $datos['documento'] = str_repeat('•', max(0, mb_strlen($datos['documento']) - 3)).mb_substr($datos['documento'], -3);
        unset($datos['modulos'], $datos['promedio']);

        return response()->json($datos);
    }

    private function lista($consulta)
    {
        return $consulta->with(['matricula.usuario', 'matricula.oferta.malla.programa', 'autorizador:id_usuario,nombres,apellidos'])
            ->orderByDesc('fecha_emision')->orderByDesc('id_certificado')->limit(500)->get()
            ->map(fn (Certificado $c) => [
                ...$c->only(['id_certificado', 'codigo_unico', 'tipo', 'estado', 'codigo_qr']),
                'fecha_emision' => $c->fecha_emision?->format('Y-m-d'),
                'estudiante' => $c->matricula->usuario->nombre_completo,
                'numero_registro' => $c->matricula->numero_registro,
                'programa' => $c->matricula->oferta->malla->programa->nombre,
                'gestion' => $c->matricula->oferta->gestion,
                'autorizado_por' => $c->autorizador?->nombre_completo,
            ]);
    }
}
