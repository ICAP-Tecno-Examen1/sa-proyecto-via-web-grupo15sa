<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\BecaEstudiante;
use App\Models\Matricula;
use App\Models\Mensualidad;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\DocumentoService;
use App\Services\MatriculaService;
use App\Services\PagoService;
use App\Services\StripeService;
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
        private readonly StripeService $stripe,
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
                'becas' => $m->becas->where('estado', BecaEstudiante::ACTIVA)->map(fn ($b) => ['beca' => $b->beca->nombre, 'porcentaje' => $b->porcentaje_aplicado])->values(),
                // Solicitudes en revisión o rechazadas, para que el estudiante sepa en qué quedó
                'solicitudes_beca' => $m->becas->whereIn('estado', [BecaEstudiante::SOLICITADA, BecaEstudiante::RECHAZADA])->sortByDesc('id_beca_estudiante')
                    ->map(fn ($b) => [
                        ...$b->only(['id_beca_estudiante', 'estado', 'porcentaje_aplicado', 'observacion_revision']),
                        'beca' => $b->beca->nombre,
                        'fecha_solicitud' => $b->fecha_solicitud?->format('Y-m-d H:i:s'),
                    ])->values(),
                'cuenta' => $this->pagos->estadoCuenta($m),
            ]);

        return response()->json($matriculas);
    }

    // ------------------------------------------------------------------
    // Registro de pagos
    // ------------------------------------------------------------------

    /**
     * Pago con comprobante o en efectivo.
     *  - Estudiante + efectivo: aviso de que pagará en caja (queda pendiente).
     *  - Personal que concilia + efectivo: cobró en caja, se confirma en el acto.
     */
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

        $actor = $request->user();
        $cobradoEnCaja = $pago->metodoPago->tipo === MetodoPago::EFECTIVO && $actor->tienePermiso('pagos.registrar') && $actor->tienePermiso('pagos.conciliar');

        if ($cobradoEnCaja) {
            $pago = $this->pagos->confirmar($pago, $actor, 'Cobrado en caja por '.$actor->nombre_completo.'.');
        }

        Auditoria::describir(match (true) {
            $cobradoEnCaja => "Cobró en efectivo Bs. {$pago->monto} de la matrícula {$cuota->matricula->numero_registro}",
            $pago->metodoPago->tipo === MetodoPago::EFECTIVO => "Avisó que pagará en caja Bs. {$pago->monto} de la matrícula {$cuota->matricula->numero_registro}",
            default => "Registró un pago de Bs. {$pago->monto} para la matrícula {$cuota->matricula->numero_registro}",
        });

        return response()->json($pago->load(self::RELACIONES), 201);
    }

    /** Inicia el pago en línea con tarjeta (Stripe Checkout) y devuelve la URL de pago de Stripe. */
    public function iniciarEnLinea(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_mensualidad' => ['required', 'integer', Rule::exists('mensualidad', 'id_mensualidad')],
            ...$this->reglasPagador(),
        ]);

        $cuota = $this->cuotaAutorizada($request->user(), (int) $datos['id_mensualidad']);
        $resultado = $this->stripe->iniciar($cuota, $datos['pagador'], $datos['parentesco_pagador'] ?? null, $request->user());

        Auditoria::describir("Inició un pago con tarjeta (Stripe) para la matrícula {$cuota->matricula->numero_registro}");

        return response()->json($resultado, 201);
    }

    /** Sondeo del cliente al volver de Stripe, hasta que el pago se confirma. */
    public function estado(Request $request, Pago $pago): JsonResponse
    {
        $this->autorizarLectura($request->user(), $pago);
        $pago = $this->stripe->consultarEstado($pago);

        return response()->json([
            'id_pago' => $pago->id_pago,
            'id_matricula' => $pago->id_matricula,
            'monto' => (float) $pago->monto,
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
            'tipo' => ['nullable', Rule::in(MetodoPago::TIPOS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $pagina = Pago::with(self::RELACIONES)
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado_conciliacion', $estado))
            ->when($f['id_metodo_pago'] ?? null, fn ($q, $id) => $q->where('id_metodo_pago', $id))
            ->when($f['tipo'] ?? null, fn ($q, $tipo) => $q->whereHas('metodoPago', fn ($m) => $m->where('tipo', $tipo)))
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

    /**
     * Avisos para la Secretaría y el Coordinador Financiero: efectivo por cobrar y
     * comprobantes por revisar (los intentos con tarjeta sin terminar no requieren acción).
     */
    public function pendientes(Request $request): JsonResponse
    {
        $pendientes = fn () => Pago::where('estado_conciliacion', Pago::PENDIENTE)
            ->whereHas('metodoPago', fn ($q) => $q->whereIn('tipo', [MetodoPago::EFECTIVO, MetodoPago::COMPROBANTE]));

        // Cuotas impagas de matrículas vigentes: informativas (no suman a la campana)
        $cuotas = fn () => Mensualidad::where('estado', Mensualidad::PENDIENTE)
            ->whereHas('matricula', fn ($m) => $m->whereIn('estado', [Matricula::PENDIENTE_PAGO, Matricula::ACTIVA]));

        return response()->json([
            'efectivo' => $pendientes()->whereHas('metodoPago', fn ($q) => $q->where('tipo', MetodoPago::EFECTIVO))->count(),
            'comprobantes' => $pendientes()->whereHas('metodoPago', fn ($q) => $q->where('tipo', MetodoPago::COMPROBANTE))->count(),
            // Solo para quien aprueba becas (Coordinador Financiero)
            'becas' => $request->user()->tienePermiso('becas.asignar') ? BecaEstudiante::where('estado', BecaEstudiante::SOLICITADA)->count() : null,
            'cuotas_vencidas' => $cuotas()->whereDate('fecha_vencimiento', '<', now()->toDateString())->count(),
            'cuotas_por_vencer' => $cuotas()->whereBetween('fecha_vencimiento', [now()->toDateString(), now()->addDays(7)->toDateString()])->count(),
            'recientes' => $pendientes()->with(self::RELACIONES)->orderByDesc('fecha')->limit(6)->get()->map(fn (Pago $p) => $this->formato($p)),
        ]);
    }

    /**
     * Cuotas por cobrar por estudiante: próxima cuota, cuántas debe, cuántas vencieron y
     * su saldo. Así la Secretaría y el Coordinador Financiero saben quién debe otra cuota.
     *   situacion = vencidas | por_vencer (próximos 7 días) | (vacío: todas)
     */
    public function porCobrar(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'situacion' => ['nullable', Rule::in(['vencidas', 'por_vencer'])],
        ]);
        $limite = now()->addDays(7)->toDateString();

        $filas = Matricula::with(MatriculaService::RELACIONES)
            ->whereIn('estado', [Matricula::PENDIENTE_PAGO, Matricula::ACTIVA])
            ->whereHas('mensualidades', fn ($q) => $q->where('estado', Mensualidad::PENDIENTE))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(numero_registro) LIKE ?', [$patron])
                    ->orWhereHas('usuario', fn ($u) => $u->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])->orWhereRaw('LOWER(ci) LIKE ?', [$patron])));
            })
            ->get()
            ->map(function (Matricula $m) {
                $cuotas = collect($this->pagos->estadoCuenta($m)['cuotas'])->where('saldo', '>', 0)->values();

                return $cuotas->isEmpty() ? null : [
                    ...$this->matriculas->resumen($m),
                    'proxima_cuota' => collect($cuotas->first())->except('pagos'),
                    'cuotas_pendientes' => $cuotas->count(),
                    'cuotas_vencidas' => $cuotas->where('vencida', true)->count(),
                    'saldo_pendiente' => round($cuotas->sum('saldo'), 2),
                    'saldo_vencido' => round($cuotas->where('vencida', true)->sum('saldo'), 2),
                    'monto_en_revision' => round($cuotas->sum('monto_en_revision'), 2),
                ];
            })
            ->filter()
            ->filter(fn ($fila) => match ($f['situacion'] ?? null) {
                'vencidas' => $fila['cuotas_vencidas'] > 0,
                'por_vencer' => ! $fila['proxima_cuota']['vencida'] && $fila['proxima_cuota']['fecha_vencimiento'] <= $limite,
                default => true,
            })
            ->sortBy(fn ($fila) => $fila['proxima_cuota']['fecha_vencimiento'])
            ->values();

        return response()->json([
            'filas' => $filas,
            'resumen' => [
                'estudiantes' => $filas->count(),
                'saldo_pendiente' => round($filas->sum('saldo_pendiente'), 2),
                'saldo_vencido' => round($filas->sum('saldo_vencido'), 2),
                'con_vencidas' => $filas->where('cuotas_vencidas', '>', 0)->count(),
            ],
        ]);
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

    /** Secretaría o Coordinador Financiero: confirma (o cobra en efectivo), observa o rechaza. */
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
