<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Matricula;
use App\Models\Mensualidad;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\DocumentoService;
use App\Services\MatriculaService;
use App\Services\PagoService;
use App\Services\PasarelaPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CU4 · Pagos: registro (comprobante, efectivo o QR), consulta y conciliación.
 */
class PagoController extends Controller
{
    private const RELACIONES = ['matricula.usuario', 'matricula.oferta.malla.programa', 'mensualidad', 'metodoPago', 'pagador',
        'registrador:id_usuario,nombres,apellidos', 'conciliador:id_usuario,nombres,apellidos'];

    public function __construct(
        private readonly PagoService $pagos,
        private readonly PasarelaPagoService $pasarela,
        private readonly MatriculaService $matriculas,
    ) {}

    // ------------------------------------------------------------------
    // Estudiante: mis pagos
    // ------------------------------------------------------------------

    /** Deudas, cuotas, saldos e historial de pagos del estudiante. */
    public function mios(Request $request): JsonResponse
    {
        $matriculas = Matricula::where('id_usuario', $request->user()->id_usuario)
            ->where('estado', '<>', Matricula::ANULADA)
            ->with([...MatriculaService::RELACIONES, 'becas.beca'])->orderByDesc('id_matricula')->get()
            ->map(fn (Matricula $m) => [
                ...$this->matriculas->resumen($m),
                'becas' => $m->becas->where('estado', 'ACTIVA')->map(fn ($b) => ['beca' => $b->beca->nombre, 'porcentaje' => $b->porcentaje_aplicado])->values(),
                'cuenta' => $this->pagos->estadoCuenta($m),
            ]);

        return response()->json($matriculas);
    }

    // ------------------------------------------------------------------
    // Registro de pagos
    // ------------------------------------------------------------------

    /** Pago con comprobante (estudiante o personal) o en efectivo (solo personal). */
    public function store(Request $request): JsonResponse
    {
        $archivos = config('icap.archivos');

        $datos = $request->validate([
            'id_mensualidad' => ['required', 'integer', Rule::exists('mensualidad', 'id_mensualidad')],
            'id_metodo_pago' => ['required', 'integer', Rule::exists('metodo_pago', 'id_metodo_pago')],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'comprobante' => ['nullable', 'file', 'mimes:'.implode(',', $archivos['extensiones']), 'max:'.$archivos['tamano_maximo_kb']],
            ...$this->reglasPagador(),
        ]);

        $cuota = $this->cuotaAutorizada($request->user(), (int) $datos['id_mensualidad']);

        $pago = $this->pagos->registrar(
            $cuota,
            MetodoPago::findOrFail($datos['id_metodo_pago']),
            $datos['pagador'],
            (float) $datos['monto'],
            $datos['referencia'] ?? null,
            $datos['parentesco_pagador'] ?? null,
            $request->file('comprobante'),
            $request->user(),
        );

        Auditoria::describir("Registró un pago de Bs. {$pago->monto} para la matrícula {$cuota->matricula->numero_registro}");

        return response()->json($pago->load(self::RELACIONES), 201);
    }

    /** Inicia el pago por QR de PagoFácil y devuelve la imagen del QR. */
    public function iniciarQr(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_mensualidad' => ['required', 'integer', Rule::exists('mensualidad', 'id_mensualidad')],
            ...$this->reglasPagador(),
        ]);

        $cuota = $this->cuotaAutorizada($request->user(), (int) $datos['id_mensualidad']);
        $resultado = $this->pasarela->iniciar($cuota, $datos['pagador'], $datos['parentesco_pagador'] ?? null, $request->user());

        Auditoria::describir("Generó un QR de pago para la matrícula {$cuota->matricula->numero_registro}");

        return response()->json($resultado, 201);
    }

    /** Sondeo del cliente mientras se muestra el QR (cada 5 segundos). */
    public function estado(Request $request, Pago $pago): JsonResponse
    {
        $this->autorizarLectura($request->user(), $pago);
        $pago = $this->pasarela->consultarEstado($pago);

        return response()->json([
            'id_pago' => $pago->id_pago,
            'estado' => $pago->estado_conciliacion,
            'estado_cuota' => $pago->mensualidad?->estado,
            'estado_matricula' => $pago->matricula->estado,
        ]);
    }

    // ------------------------------------------------------------------
    // Personal: consulta y conciliación
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(Pago::ESTADOS)],
            'id_metodo_pago' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $pagina = Pago::with(self::RELACIONES)
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado_conciliacion', $estado))
            ->when($f['id_metodo_pago'] ?? null, fn ($q, $id) => $q->where('id_metodo_pago', $id))
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($f['hasta'] ?? null, fn ($q, $h) => $q->whereDate('fecha', '<=', $h))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(referencia) LIKE ?', [$patron])
                    ->orWhereHas('matricula', fn ($m) => $m->whereRaw('LOWER(numero_registro) LIKE ?', [$patron]))
                    ->orWhereHas('matricula.usuario', fn ($u) => $u->where(fn ($x) => $x
                        ->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])->orWhereRaw('LOWER(ci) LIKE ?', [$patron]))));
            })
            ->orderByRaw("CASE estado_conciliacion WHEN 'PENDIENTE' THEN 0 ELSE 1 END")
            ->orderByDesc('fecha')
            ->paginate($f['por_pagina'] ?? 15);

        $pagina->getCollection()->transform(fn (Pago $p) => $this->formato($p));

        return response()->json($pagina);
    }

    public function show(Pago $pago): JsonResponse
    {
        return response()->json($this->formato($pago->load(self::RELACIONES)));
    }

    /** Cuotas pendientes de una matrícula (para que el personal registre un pago). */
    public function cuotasDeMatricula(Matricula $matricula): JsonResponse
    {
        $matricula->load(MatriculaService::RELACIONES);

        return response()->json([
            ...$this->matriculas->resumen($matricula),
            'cuenta' => $this->pagos->estadoCuenta($matricula),
        ]);
    }

    /** Coordinador Financiero: confirma, observa o rechaza. */
    public function conciliar(Request $request, Pago $pago): JsonResponse
    {
        $datos = $request->validate([
            'decision' => ['required', Rule::in([Pago::CONFIRMADO, Pago::OBSERVADO, Pago::RECHAZADO])],
            'observacion' => ['nullable', 'required_unless:decision,'.Pago::CONFIRMADO, 'string', 'min:5', 'max:500'],
        ], ['observacion.required_unless' => 'Debe explicar el motivo de la observación o del rechazo.']);

        $pago = $this->pagos->conciliar($pago, $datos['decision'], $datos['observacion'] ?? null, $request->user());
        Auditoria::describir("Concilió el pago #{$pago->id_pago} como {$datos['decision']}");

        return response()->json($this->formato($pago->load(self::RELACIONES)));
    }

    public function comprobante(Request $request, Pago $pago, DocumentoService $documentos): StreamedResponse
    {
        $this->autorizarLectura($request->user(), $pago);

        return $documentos->entregar($pago->comprobante_url, 'comprobante-pago-'.$pago->id_pago, true);
    }

    // ------------------------------------------------------------------

    private function reglasPagador(): array
    {
        return [
            'parentesco_pagador' => ['nullable', 'string', 'max:50'],
            'pagador' => ['required', 'array'],
            'pagador.nombres' => ['required', 'string', 'min:2', 'max:150'],
            'pagador.apellidos' => ['required', 'string', 'min:2', 'max:150'],
            'pagador.tipo_documento' => ['required', Rule::in([...Usuario::TIPOS_DOCUMENTO, 'NIT'])],
            'pagador.numero_documento' => ['required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'pagador.telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]{6,30}$/'],
            'pagador.correo' => ['nullable', 'email', 'max:150'],
        ];
    }

    /** El estudiante solo paga cuotas propias; el personal necesita pagos.registrar. */
    private function cuotaAutorizada(Usuario $usuario, int $idMensualidad): Mensualidad
    {
        $cuota = Mensualidad::with('matricula')->findOrFail($idMensualidad);

        if ($cuota->matricula->id_usuario !== $usuario->id_usuario) {
            abort_unless($usuario->tienePermiso('pagos.registrar'), 403, 'Solo puede pagar cuotas de sus propias matrículas.');
        } else {
            abort_unless($usuario->tieneAlgunPermiso(['pagos.propios', 'pagos.registrar']), 403, 'Su rol no puede registrar pagos.');
        }

        return $cuota;
    }

    private function autorizarLectura(Usuario $usuario, Pago $pago): void
    {
        $propio = $pago->matricula->id_usuario === $usuario->id_usuario;

        abort_unless($propio || $usuario->tieneAlgunPermiso(['pagos.ver', 'pagos.conciliar']), 403, 'No puede consultar este pago.');
    }

    private function formato(Pago $p): array
    {
        return [
            ...$p->only(['id_pago', 'monto', 'referencia', 'estado_conciliacion', 'parentesco_pagador', 'observacion_conciliacion',
                'numero_transaccion', 'tiene_comprobante', 'id_matricula', 'id_mensualidad']),
            'fecha' => $p->fecha?->format('Y-m-d H:i:s'),
            'fecha_conciliacion' => $p->fecha_conciliacion?->format('Y-m-d H:i:s'),
            'cuota' => $p->mensualidad?->only(['numero_cuota', 'monto_final', 'estado']),
            'metodo' => $p->metodoPago->only(['id_metodo_pago', 'nombre', 'tipo']),
            'pagador' => $p->pagador,
            'registrado_por' => $p->registrador?->nombre_completo,
            'conciliado_por' => $p->conciliador?->nombre_completo ?? ($p->estado_conciliacion === Pago::CONFIRMADO && ! $p->id_usuario_conciliador ? 'Pasarela (automático)' : null),
            'matricula' => [
                'numero_registro' => $p->matricula->numero_registro,
                'estado' => $p->matricula->estado,
                'estudiante' => $p->matricula->usuario->nombre_completo,
                'ci' => $p->matricula->usuario->ci,
                'programa' => $p->matricula->oferta->malla->programa->nombre,
                'oferta' => $p->matricula->oferta->codigo,
            ],
        ];
    }
}
