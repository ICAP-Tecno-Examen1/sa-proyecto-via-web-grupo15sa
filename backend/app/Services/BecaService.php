<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Beca;
use App\Models\BecaEstudiante;
use App\Models\Matricula;
use App\Models\Mensualidad;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * CU4 · Becas de los estudiantes.
 *
 * La mayoría de las becas son de CONVENIOS: una institución o empresa (beca.institucion_financiadora)
 * envía a sus estudiantes o empleados a un curso. Una beca solo aplica si está ACTIVA y
 * cubre el curso de la matrícula. El vínculo con el estudiante es beca_estudiante.
 *
 * ¿Cómo se sabe que el estudiante está habilitado? Lo SOLICITA adjuntando el respaldo
 * (obligatorio si la beca es de una institución: credencial o carta de la institución) y el
 * Coordinador Financiero lo revisa: aprueba (puede ajustar el porcentaje) o rechaza con
 * el motivo. Solo una beca aprobada descuenta: se aplica a las cuotas aún no pagadas.
 */
class BecaService
{
    public function __construct(private readonly MatriculaService $matriculas) {}

    /** El estudiante pide una beca para su matrícula, con documento de respaldo. */
    public function solicitar(Matricula $matricula, Beca $beca, ?string $motivo, ?UploadedFile $respaldo): BecaEstudiante
    {
        $this->exigirMatriculaVigente($matricula);
        $this->exigirAplicable($beca, $matricula);

        if (! $respaldo && $beca->requiereRespaldo()) {
            throw new ReglaNegocioException("Adjunte el documento de «{$beca->institucion_financiadora}» (credencial, carta o constancia de la institución).");
        }

        if (! $matricula->mensualidades()->where('estado', Mensualidad::PENDIENTE)->exists()) {
            throw new ReglaNegocioException('Esta matrícula ya no tiene cuotas por pagar a las que aplicar una beca.');
        }

        $abierta = $matricula->becas()->where('id_beca', $beca->id_beca)
            ->whereIn('estado', [BecaEstudiante::SOLICITADA, BecaEstudiante::ACTIVA])->first();

        if ($abierta) {
            throw new ReglaNegocioException($abierta->estado === BecaEstudiante::ACTIVA
                ? 'Ya tiene esta beca aplicada.'
                : 'Ya solicitó esta beca: está en revisión del Coordinador Financiero.');
        }

        return BecaEstudiante::create([
            'id_beca' => $beca->id_beca,
            'id_matricula' => $matricula->id_matricula,
            'porcentaje_aplicado' => $beca->porcentaje,
            'estado' => BecaEstudiante::SOLICITADA,
            'fecha_asignacion' => now()->toDateString(),
            'fecha_solicitud' => now(),
            'motivo' => $motivo,
            'respaldo_url' => $respaldo?->store('becas/'.$matricula->id_matricula),
        ]);
    }

    /** El Coordinador Financiero revisó el respaldo y la aprueba: se aplica el descuento. */
    public function aprobar(BecaEstudiante $solicitud, ?float $porcentaje, ?string $observacion, Usuario $revisor): BecaEstudiante
    {
        if ($solicitud->estado !== BecaEstudiante::SOLICITADA) {
            throw new ReglaNegocioException('Esta solicitud ya fue revisada.');
        }

        $matricula = $solicitud->matricula;
        $this->exigirMatriculaVigente($matricula);
        // Entre la solicitud y la revisión la beca pudo desactivarse.
        $this->exigirAplicable($solicitud->beca, $matricula);
        $porcentaje ??= (float) $solicitud->beca->porcentaje;
        $this->exigirTope($matricula, $porcentaje);

        DB::transaction(function () use ($solicitud, $matricula, $porcentaje, $observacion, $revisor) {
            $solicitud->update([
                'estado' => BecaEstudiante::ACTIVA,
                'porcentaje_aplicado' => $porcentaje,
                'fecha_asignacion' => now()->toDateString(),
                'id_usuario_revisor' => $revisor->id_usuario,
                'fecha_revision' => now(),
                'observacion_revision' => $observacion,
            ]);

            $this->matriculas->recalcularMontos($matricula);
        });

        return $solicitud;
    }

    /** El respaldo no corresponde: se rechaza explicando el motivo. */
    public function rechazar(BecaEstudiante $solicitud, string $motivo, Usuario $revisor): BecaEstudiante
    {
        if ($solicitud->estado !== BecaEstudiante::SOLICITADA) {
            throw new ReglaNegocioException('Esta solicitud ya fue revisada.');
        }

        $solicitud->update([
            'estado' => BecaEstudiante::RECHAZADA,
            'id_usuario_revisor' => $revisor->id_usuario,
            'fecha_revision' => now(),
            'observacion_revision' => $motivo,
        ]);

        return $solicitud;
    }

    /** Asignación directa del Coordinador Financiero (por ejemplo, por convenio ya verificado). */
    public function asignar(Matricula $matricula, Beca $beca, ?float $porcentaje, Usuario $revisor): BecaEstudiante
    {
        $this->exigirMatriculaVigente($matricula);
        $this->exigirAplicable($beca, $matricula);

        if ($matricula->becas()->where('id_beca', $beca->id_beca)->where('estado', BecaEstudiante::ACTIVA)->exists()) {
            throw new ReglaNegocioException('La matrícula ya tiene asignada esta beca.');
        }

        $porcentaje ??= (float) $beca->porcentaje;
        $this->exigirTope($matricula, $porcentaje);

        return DB::transaction(function () use ($matricula, $beca, $porcentaje, $revisor) {
            // Si el estudiante ya la había solicitado, la solicitud queda aprobada en lugar de duplicarse.
            $becaEstudiante = $matricula->becas()->where('id_beca', $beca->id_beca)->where('estado', BecaEstudiante::SOLICITADA)->first()
                ?? new BecaEstudiante(['id_beca' => $beca->id_beca, 'id_matricula' => $matricula->id_matricula]);

            $becaEstudiante->fill([
                'porcentaje_aplicado' => $porcentaje,
                'estado' => BecaEstudiante::ACTIVA,
                'fecha_asignacion' => now()->toDateString(),
                'id_usuario_revisor' => $revisor->id_usuario,
                'fecha_revision' => now(),
            ])->save();

            $this->matriculas->recalcularMontos($matricula);

            return $becaEstudiante;
        });
    }

    private function exigirAplicable(Beca $beca, Matricula $matricula): void
    {
        if ($motivo = $beca->motivoNoAplica($matricula)) {
            throw new ReglaNegocioException($motivo);
        }
    }

    private function exigirMatriculaVigente(Matricula $matricula): void
    {
        if (! in_array($matricula->estado, [Matricula::PENDIENTE_PAGO, Matricula::ACTIVA], true)) {
            throw new ReglaNegocioException('Solo se aplican becas a matrículas pendientes de pago o activas.');
        }
    }

    private function exigirTope(Matricula $matricula, float $porcentaje): void
    {
        $actual = (float) $matricula->becas()->where('estado', BecaEstudiante::ACTIVA)->sum('porcentaje_aplicado');

        if ($actual + $porcentaje > 100) {
            throw new ReglaNegocioException('La suma de las becas de la matrícula no puede superar el 100% (ya tiene '.round($actual, 2).'%).');
        }
    }
}
