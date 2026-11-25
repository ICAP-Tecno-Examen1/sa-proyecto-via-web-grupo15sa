<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\MallaCurricular;
use App\Models\OfertaAcademica;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CU2 · Ofertas académicas.
 *
 * Una oferta se crea a partir de UNA versión de malla y hereda todos sus
 * módulos (oferta_modulo). La malla de una oferta ya creada no se cambia.
 */
class OfertaService
{
    public function crear(array $datos): OfertaAcademica
    {
        $malla = MallaCurricular::with(['mallaModulos', 'bloques'])->findOrFail($datos['id_malla']);

        if ($malla->estado !== MallaCurricular::ACTIVO) {
            throw new ReglaNegocioException('Solo se puede ofertar una malla curricular activa.');
        }

        if ($malla->mallaModulos->isEmpty()) {
            throw new ReglaNegocioException('La malla seleccionada no tiene módulos.');
        }

        $calendario = $this->calendarioPorModulos($malla, Carbon::parse($datos['fecha_inicio']));

        // En una carrera, la oferta termina cuando termina su último módulo.
        if ($calendario) {
            $datos['fecha_fin'] = collect($calendario)->last()['fin']->toDateString();
        }

        return DB::transaction(function () use ($datos, $malla, $calendario) {
            $oferta = OfertaAcademica::create([
                ...$datos,
                'estado' => OfertaAcademica::PLANIFICADA,
            ]);

            foreach ($malla->mallaModulos as $mallaModulo) {
                $periodo = $calendario[$mallaModulo->id_modulo_carrera] ?? null;

                $oferta->ofertaModulos()->create([
                    'id_malla_modulo' => $mallaModulo->id_malla_modulo,
                    'fecha_inicio' => $periodo ? $periodo['inicio']->toDateString() : $oferta->fecha_inicio,
                    'fecha_fin' => $periodo ? $periodo['fin']->toDateString() : $oferta->fecha_fin,
                ]);
            }

            return $oferta;
        });
    }

    /**
     * Calendario de una carrera: el módulo N se dicta en el mes N desde el inicio de la
     * oferta (config icap.academico.meses_por_modulo) y sus 1 o 2 materias comparten ese
     * periodo. Devuelve [id_modulo_carrera => [inicio, fin]], o [] si la malla no usa módulos.
     */
    public function calendarioPorModulos(MallaCurricular $malla, Carbon $inicio): array
    {
        $meses = (int) config('icap.academico.meses_por_modulo', 1);
        $calendario = [];

        foreach ($malla->bloques->sortBy('orden')->values() as $i => $bloque) {
            $desde = $inicio->copy()->addMonthsNoOverflow($i * $meses);

            $calendario[$bloque->id_modulo_carrera] = [
                'inicio' => $desde,
                'fin' => $inicio->copy()->addMonthsNoOverflow(($i + 1) * $meses)->subDay(),
            ];
        }

        return $calendario;
    }

    public function actualizar(OfertaAcademica $oferta, array $datos): OfertaAcademica
    {
        unset($datos['id_malla']);

        if (isset($datos['cupo_maximo']) && $datos['cupo_maximo'] < $oferta->cuposOcupados()) {
            throw new ReglaNegocioException(
                'El cupo máximo no puede ser menor que la cantidad de estudiantes ya matriculados ('.$oferta->cuposOcupados().').'
            );
        }

        $oferta->update($datos);

        return $oferta;
    }

    public function cambiarEstado(OfertaAcademica $oferta, string $estado): OfertaAcademica
    {
        if ($estado === OfertaAcademica::CANCELADA && $oferta->cuposOcupados() > 0) {
            throw new ReglaNegocioException('No se puede cancelar una oferta que ya tiene estudiantes matriculados.');
        }

        if ($estado === OfertaAcademica::ABIERTA && ! $oferta->aranceles()->where('estado', 'ACTIVO')->exists()) {
            throw new ReglaNegocioException(
                'Antes de abrir inscripciones, el Coordinador Financiero debe configurar al menos un plan de pago (arancel).'
            );
        }

        $oferta->update(['estado' => $estado]);

        return $oferta;
    }

    // ------------------------------------------------------------------
    // Presentación (catálogo público y pantallas de gestión)
    // ------------------------------------------------------------------

    /** Relaciones que necesita resumen(). */
    public const RELACIONES = ['malla.programa', 'malla.mallaModulos', 'malla.bloques', 'aranceles'];

    /** Ficha corta de la oferta: programa, duración, modalidad, cupos y costos. */
    public function resumen(OfertaAcademica $oferta): array
    {
        $ocupados = $oferta->matriculas_ocupadas ?? $oferta->cuposOcupados();
        $programa = $oferta->malla->programa;
        $planes = $oferta->aranceles->where('estado', 'ACTIVO')->sortBy('monto')->values();

        return [
            ...$oferta->only(['id_oferta', 'codigo', 'estado', 'modalidad', 'cupo_maximo', 'cupo_minimo', 'gestion', 'id_malla']),
            'fecha_inicio' => $oferta->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $oferta->fecha_fin?->format('Y-m-d'),
            'cupos_ocupados' => (int) $ocupados,
            'cupos_disponibles' => max(0, $oferta->cupo_maximo - $ocupados),
            'programa' => $programa->only(['id_programa', 'codigo', 'nombre', 'descripcion', 'tipo']),
            'malla' => [
                'id_malla' => $oferta->malla->id_malla,
                'version' => $oferta->malla->version,
                'horas_totales' => $oferta->malla->horas_totales,
                'cantidad_modulos' => $oferta->malla->mallaModulos->count(),
                // Carreras: módulos que agrupan 1 o 2 materias (0 si la malla no los usa)
                'cantidad_bloques' => $oferta->malla->bloques->count(),
            ],
            'planes_pago' => $planes->map(fn ($a) => $a->only(['id_arancel', 'concepto', 'monto', 'tipo_cobro', 'numero_cuotas'])),
            'costo_desde' => $planes->min('monto'),
        ];
    }

    /** Consulta base con el conteo de cupos ocupados ya resuelto (evita una consulta por oferta). */
    public function consulta()
    {
        return OfertaAcademica::with(self::RELACIONES)
            ->withCount(['matriculas as matriculas_ocupadas' => fn ($q) => $q->where('estado', '<>', 'ANULADA')]);
    }

    /** Ficha completa: además lista los módulos con sus docentes. */
    public function detalle(OfertaAcademica $oferta, bool $conContratos = false): array
    {
        $oferta->load([...self::RELACIONES, 'ofertaModulos.mallaModulo.modulo', 'ofertaModulos.mallaModulo.bloque', 'ofertaModulos.asignaciones.docente']);

        $modulos = $oferta->ofertaModulos
            ->sortBy(fn ($om) => $om->mallaModulo->orden)
            ->map(fn ($om) => [
                'id_oferta_modulo' => $om->id_oferta_modulo,
                'estado' => $om->estado,
                'fecha_inicio' => $om->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $om->fecha_fin?->format('Y-m-d'),
                'orden' => $om->mallaModulo->orden,
                'obligatorio' => $om->mallaModulo->obligatorio,
                'codigo' => $om->mallaModulo->modulo->codigo,
                'nombre' => $om->mallaModulo->modulo->nombre,
                'descripcion' => $om->mallaModulo->modulo->descripcion,
                'horas' => $om->mallaModulo->modulo->horas,
                'bloque' => $om->mallaModulo->bloque?->only(['id_modulo_carrera', 'nombre', 'orden']),
                'docentes' => $om->asignaciones->where('estado', 'ACTIVO')->map(fn ($a) => $a->docente->nombre_completo)->values(),
                // Los datos de contratación y honorarios solo viajan a quien gestiona la oferta.
                'asignaciones' => $conContratos
                    ? $om->asignaciones->map(fn ($a) => [...$a->attributesToArray(), 'docente' => $a->docente->only(['id_usuario', 'nombre_completo', 'ci'])])->values()
                    : null,
            ])->values();

        return [...$this->resumen($oferta), 'modulos' => $modulos];
    }
}
