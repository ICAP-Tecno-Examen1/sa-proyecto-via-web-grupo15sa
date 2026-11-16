<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\MallaCurricular;
use App\Models\Modulo;
use Illuminate\Support\Facades\DB;

/**
 * CU2 · Mallas curriculares y su versionado.
 *
 * Reglas:
 *  - La versión no se repite dentro de un mismo programa.
 *  - Las horas totales son la suma de las horas de sus módulos.
 *  - Una malla que ya fue usada en una oferta académica no cambia sus módulos:
 *    se debe crear una nueva versión.
 */
class MallaService
{
    public function crear(array $datos): MallaCurricular
    {
        $this->validarVersionUnica($datos['id_programa'], $datos['version']);

        return DB::transaction(function () use ($datos) {
            $malla = MallaCurricular::create([
                'id_programa' => $datos['id_programa'],
                'version' => $datos['version'],
                'estado' => $datos['estado'] ?? MallaCurricular::ACTIVO,
                'fecha_vigencia' => $datos['fecha_vigencia'] ?? null,
                'horas_totales' => $this->horasTotales($datos['modulos']),
            ]);

            $this->guardarModulos($malla, $datos['modulos']);

            return $malla;
        });
    }

    public function actualizar(MallaCurricular $malla, array $datos): MallaCurricular
    {
        if (isset($datos['version']) && $datos['version'] !== $malla->version) {
            $this->validarVersionUnica($malla->id_programa, $datos['version'], $malla->id_malla);
        }

        return DB::transaction(function () use ($malla, $datos) {
            if (array_key_exists('modulos', $datos)) {
                if ($malla->ofertas()->exists() && $this->cambianModulos($malla, $datos['modulos'])) {
                    throw new ReglaNegocioException(
                        'Esta malla ya se usa en una oferta académica: no se pueden cambiar sus módulos. Cree una nueva versión.'
                    );
                }

                if (! $malla->ofertas()->exists()) {
                    $malla->mallaModulos()->delete();
                    $this->guardarModulos($malla, $datos['modulos']);
                    $malla->horas_totales = $this->horasTotales($datos['modulos']);
                }
            }

            $malla->fill(array_intersect_key($datos, array_flip(['version', 'estado', 'fecha_vigencia'])))->save();

            return $malla;
        });
    }

    /** Crea una nueva versión copiando los módulos de una malla existente. */
    public function versionar(MallaCurricular $origen, string $version, ?string $fechaVigencia): MallaCurricular
    {
        $modulos = $origen->mallaModulos()->get(['id_modulo', 'orden', 'obligatorio'])->toArray();

        return $this->crear([
            'id_programa' => $origen->id_programa,
            'version' => $version,
            'fecha_vigencia' => $fechaVigencia,
            'modulos' => $modulos,
        ]);
    }

    private function validarVersionUnica(int $idPrograma, string $version, ?int $excepto = null): void
    {
        $existe = MallaCurricular::where('id_programa', $idPrograma)
            ->whereRaw('LOWER(version) = ?', [mb_strtolower($version)])
            ->when($excepto, fn ($q) => $q->where('id_malla', '<>', $excepto))
            ->exists();

        if ($existe) {
            throw new ReglaNegocioException("El programa ya tiene una malla con la versión «{$version}».");
        }
    }

    private function horasTotales(array $modulos): int
    {
        return (int) Modulo::whereIn('id_modulo', array_column($modulos, 'id_modulo'))->sum('horas');
    }

    private function guardarModulos(MallaCurricular $malla, array $modulos): void
    {
        foreach (array_values($modulos) as $i => $modulo) {
            $malla->mallaModulos()->create([
                'id_modulo' => $modulo['id_modulo'],
                'orden' => $modulo['orden'] ?? $i + 1,
                'obligatorio' => $modulo['obligatorio'] ?? true,
            ]);
        }
    }

    private function cambianModulos(MallaCurricular $malla, array $modulos): bool
    {
        $actuales = $malla->mallaModulos()->pluck('id_modulo')->sort()->values()->all();
        $nuevos = collect($modulos)->pluck('id_modulo')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $actuales !== $nuevos;
    }
}
