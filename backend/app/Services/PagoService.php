<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Matricula;
use App\Models\Mensualidad;
use App\Models\MetodoPago;
use App\Models\Pagador;
use App\Models\Pago;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * CU4 · Registro y conciliación de pagos.
 *
 * Todo pago nace PENDIENTE y corresponde a una cuota (mensualidad):
 *   - EFECTIVO y COMPROBANTE los revisa el Coordinador Financiero
 *     (CONFIRMADO, OBSERVADO o RECHAZADO).
 *   - PASARELA (tarjeta con Stripe) se confirma sola, por el webhook o la consulta a Stripe.
 *
 * Al confirmar: el trigger trg_pago_estado_mensualidad marca la cuota como
 * PAGADA si quedó cubierta, y este servicio activa la matrícula cuando la
 * cuota pagada es la inicial.
 */
class PagoService
{
    public function __construct(
        private readonly MatriculaService $matriculas,
        private readonly DocumentoService $documentos,
    ) {}

    /** Registra un pago en efectivo o con comprobante, a la espera de conciliación. */
    public function registrar(
        Mensualidad $cuota,
        MetodoPago $metodo,
        array $datosPagador,
        float $monto,
        ?string $referencia,
        ?string $parentesco,
        ?UploadedFile $comprobante,
        Usuario $actor,
    ): Pago {
        $matricula = $this->exigirCuotaPagable($cuota);

        if (! $metodo->activo) {
            throw new ReglaNegocioException('El método de pago seleccionado no está habilitado.');
        }

        if ($metodo->tipo === MetodoPago::PASARELA) {
            throw new ReglaNegocioException('Los pagos con tarjeta se inician desde la opción «Pagar con tarjeta» (Stripe).');
        }

        // El estudiante que elige efectivo solo AVISA que pagará en caja: queda pendiente por el
        // saldo de la cuota hasta que la Secretaría o el Coordinador Financiero lo cobre.
        if ($metodo->tipo === MetodoPago::EFECTIVO && ! $actor->tienePermiso('pagos.registrar')) {
            $monto = $cuota->saldo();
            $referencia = $referencia ?: 'Pagará en caja del ICAP';
        }

        if ($metodo->tipo === MetodoPago::COMPROBANTE && ! $comprobante) {
            throw new ReglaNegocioException('Debe adjuntar el comprobante del pago.');
        }

        $saldo = $cuota->saldo();

        if ($monto > $saldo + 0.001) {
            throw new ReglaNegocioException('El monto supera el saldo de la cuota (Bs. '.number_format($saldo, 2).').');
        }

        $enRevision = $cuota->pagos()->where('estado_conciliacion', Pago::PENDIENTE)
            ->whereHas('metodoPago', fn ($q) => $q->where('tipo', '<>', MetodoPago::PASARELA))->exists();

        if ($enRevision) {
            throw new ReglaNegocioException('Esta cuota ya tiene un pago en revisión. Espere la conciliación antes de registrar otro.');
        }

        return DB::transaction(function () use ($cuota, $matricula, $metodo, $datosPagador, $monto, $referencia, $parentesco, $comprobante, $actor) {
            return Pago::create([
                'id_matricula' => $matricula->id_matricula,
                'id_arancel' => $cuota->id_arancel,
                'id_mensualidad' => $cuota->id_mensualidad,
                'id_metodo_pago' => $metodo->id_metodo_pago,
                'id_pagador' => $this->resolverPagador($datosPagador)->id_pagador,
                'monto' => round($monto, 2),
                'fecha' => now(),
                'referencia' => $referencia,
                'comprobante_url' => $comprobante?->store('comprobantes/'.$matricula->id_matricula),
                'estado_conciliacion' => Pago::PENDIENTE,
                'parentesco_pagador' => $parentesco,
                'id_usuario_registro' => $actor->id_usuario,
            ]);
        });
    }

    /** Decisión de la Secretaría o del Coordinador Financiero sobre un pago pendiente. */
    public function conciliar(Pago $pago, string $decision, ?string $observacion, Usuario $conciliador): Pago
    {
        if ($pago->estado_conciliacion !== Pago::PENDIENTE) {
            throw new ReglaNegocioException('Este pago ya fue conciliado.');
        }

        if ($decision === Pago::CONFIRMADO) {
            return $this->confirmar($pago, $conciliador, $observacion);
        }

        $pago->update([
            'estado_conciliacion' => $decision,
            'observacion_conciliacion' => $observacion,
            'id_usuario_conciliador' => $conciliador->id_usuario,
            'fecha_conciliacion' => now(),
        ]);

        return $pago;
    }

    /**
     * Confirma un pago. Es idempotente: un pago ya confirmado no se vuelve a
     * procesar (el webhook de la pasarela puede llegar repetido).
     *
     * @param  ?Usuario  $conciliador  null cuando confirma la pasarela automáticamente
     */
    public function confirmar(Pago $pago, ?Usuario $conciliador, ?string $observacion = null): Pago
    {
        return DB::transaction(function () use ($pago, $conciliador, $observacion) {
            $pago = Pago::lockForUpdate()->findOrFail($pago->id_pago);

            if ($pago->estado_conciliacion === Pago::CONFIRMADO) {
                return $pago;
            }

            $cuota = $pago->mensualidad;

            // Una confirmación manual no puede exceder lo que falta pagar. La pasarela
            // se confirma siempre: el dinero ya ingresó y debe quedar registrado.
            if ($conciliador && $cuota && $pago->monto > $cuota->saldo() + 0.001) {
                throw new ReglaNegocioException('El monto del pago supera el saldo actual de la cuota. Rechace u observe el pago.');
            }

            $pago->update([
                'estado_conciliacion' => Pago::CONFIRMADO,
                'observacion_conciliacion' => $observacion,
                'id_usuario_conciliador' => $conciliador?->id_usuario,
                'fecha_conciliacion' => now(),
            ]);

            $matricula = $pago->matricula;
            $this->matriculas->verificarActivacion($matricula);
            $this->archivarComprobante($pago, $matricula, $conciliador);

            return $pago->refresh();
        });
    }

    /** Estado de cuenta de una matrícula: cuotas, pagos, saldos y deuda vencida. */
    public function estadoCuenta(Matricula $matricula): array
    {
        $hoy = now()->toDateString();
        $cuotas = $matricula->mensualidades()->with(['pagos.metodoPago', 'pagos.pagador'])->get();

        $detalle = $cuotas->map(function (Mensualidad $c) use ($hoy) {
            $pagado = (float) $c->pagos->where('estado_conciliacion', Pago::CONFIRMADO)->sum('monto');
            $saldo = $c->estado === Mensualidad::PENDIENTE ? max(0, round($c->monto_final - $pagado, 2)) : 0.0;
            $enRevision = (float) $c->pagos->where('estado_conciliacion', Pago::PENDIENTE)
                ->filter(fn (Pago $p) => $p->metodoPago?->tipo !== MetodoPago::PASARELA)->sum('monto');

            return [
                ...$c->only(['id_mensualidad', 'numero_cuota', 'monto_original', 'monto_descuento', 'monto_final', 'estado']),
                'fecha_vencimiento' => $c->fecha_vencimiento->format('Y-m-d'),
                'monto_pagado' => round($pagado, 2),
                'saldo' => $saldo,
                'monto_en_revision' => round($enRevision, 2),
                'vencida' => $saldo > 0 && $c->fecha_vencimiento->format('Y-m-d') < $hoy,
                'pagos' => $c->pagos->sortByDesc('fecha')->values(),
            ];
        });

        return [
            'cuotas' => $detalle,
            'resumen' => [
                'monto_original' => $matricula->monto_original,
                'monto_descuento' => $matricula->monto_descuento,
                'monto_final' => $matricula->monto_final,
                'total_pagado' => round($detalle->sum('monto_pagado'), 2),
                'saldo_pendiente' => round($detalle->sum('saldo'), 2),
                'saldo_vencido' => round($detalle->where('vencida', true)->sum('saldo'), 2),
                'monto_en_revision' => round($detalle->sum('monto_en_revision'), 2),
                'proxima_cuota' => $detalle->firstWhere('saldo', '>', 0),
            ],
        ];
    }

    /** Valida que la cuota pueda recibir un pago y devuelve su matrícula. */
    public function exigirCuotaPagable(Mensualidad $cuota): Matricula
    {
        $matricula = $cuota->matricula;

        if ($matricula->estado === Matricula::ANULADA) {
            throw new ReglaNegocioException('La matrícula está anulada; no admite pagos.');
        }

        if ($cuota->estado !== Mensualidad::PENDIENTE || $cuota->saldo() <= 0) {
            throw new ReglaNegocioException('Esta cuota ya está pagada.');
        }

        return $matricula;
    }

    /** Reutiliza al pagador si ya existe con el mismo documento; si no, lo crea. */
    public function resolverPagador(array $datos): Pagador
    {
        $datos = array_intersect_key($datos, array_flip(['nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'telefono', 'correo']));

        if (! empty($datos['numero_documento'])) {
            return Pagador::updateOrCreate(
                ['tipo_documento' => $datos['tipo_documento'], 'numero_documento' => $datos['numero_documento']],
                $datos,
            );
        }

        return Pagador::create($datos);
    }

    /** CU7: el comprobante de un pago confirmado pasa al repositorio como documento financiero. */
    private function archivarComprobante(Pago $pago, Matricula $matricula, ?Usuario $conciliador): void
    {
        if (! $pago->comprobante_url) {
            return;
        }

        $this->documentos->registrar([
            'nombre' => 'Comprobante de pago · '.$matricula->numero_registro.' · cuota '.($pago->mensualidad?->numero_cuota ?? '-'),
            'descripcion' => 'Pago de Bs. '.number_format($pago->monto, 2).' confirmado el '.now()->format('d/m/Y').'.',
            'tipo' => 'COMPROBANTE_PAGO',
            'categoria' => 'FINANCIERO',
            'gestion' => $matricula->oferta->gestion,
            'url_archivo' => $pago->comprobante_url,
        ], null, $conciliador, [$matricula->id_usuario]);
    }
}
