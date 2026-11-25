<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\MallaCurricular;
use App\Models\MallaModulo;
use App\Models\Modulo;
use App\Models\ModuloCarrera;
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
        $datos = $this->normalizarBloques($datos);

        return DB::transaction(function () use ($datos) {
            $malla = MallaCurricular::create([
                'id_programa' => $datos['id_programa'],
                'version' => $datos['version'],
                'estado' => $datos['estado'] ?? MallaCurricular::ACTIVO,
                'fecha_vigencia' => $datos['fecha_vigencia'] ?? null,
                'horas_totales' => $this->horasTotales($datos['modulos']),
            ]);

            $this->guardarPlan($malla, $datos);

            return $malla;
        });
    }

    /**
     * Guarda el plan de estudios con un número FIJO de consultas, sin importar cuántas
     * materias tenga: primero los módulos de la carrera (si hay) y luego todas las
     * materias de una vez, cada una ya con su módulo. Importa cuando la base de datos
     * está lejos (cada consulta puede tardar ~1 s y PHP corta a los 30 s).
     */
    private function guardarPlan(MallaCurricular $malla, array $datos): void
    {
        $moduloDeMateria = empty($datos['bloques']) ? [] : $this->guardarBloques($malla, $datos['bloques']);
        $this->guardarModulos($malla, $datos['modulos'], $moduloDeMateria);
    }

    /**
     * Módulos de la carrera: cada uno agrupa 1 o 2 materias. Las materias se aplanan
     * a la lista de la malla (en el orden de los bloques) para calcular horas y orden.
     */
    private function normalizarBloques(array $datos): array
    {
        if (empty($datos['bloques'])) {
            return $datos;
        }

        $modulos = [];

        foreach ($datos['bloques'] as $bloque) {
            $cantidad = count($bloque['modulos']);

            if ($cantidad < 1 || $cantidad > ModuloCarrera::MAXIMO_MATERIAS) {
                throw new ReglaNegocioException("El módulo «{$bloque['nombre']}» debe tener entre 1 y 2 materias (tiene {$cantidad}).");
            }

            foreach ($bloque['modulos'] as $materia) {
                if (in_array((int) $materia['id_modulo'], array_column($modulos, 'id_modulo'), true)) {
                    $nombre = Modulo::find($materia['id_modulo'])?->nombre ?? 'seleccionada';
                    throw new ReglaNegocioException("La materia «{$nombre}» está en más de un módulo de la carrera.");
                }

                $modulos[] = [
                    'id_modulo' => (int) $materia['id_modulo'],
                    'orden' => count($modulos) + 1,
                    'obligatorio' => $materia['obligatorio'] ?? true,
                ];
            }
        }

        $datos['modulos'] = $modulos;

        return $datos;
    }

    /**
     * Crea todos los módulos de la carrera en UNA consulta (INSERT … RETURNING de PostgreSQL)
     * y devuelve a qué módulo pertenece cada materia: [id_modulo => id_modulo_carrera].
     */
    private function guardarBloques(MallaCurricular $malla, array $bloques): array
    {
        $bloques = array_values($bloques);
        $valores = [];
        $parametros = [];

        foreach ($bloques as $i => $bloque) {
            $valores[] = '(?, ?, ?)';
            array_push($parametros, $malla->id_malla, $bloque['nombre'], $bloque['orden'] ?? $i + 1);
        }

        $creados = DB::select(
            'INSERT INTO modulo_carrera (id_malla, nombre, orden) VALUES '.implode(', ', $valores).' RETURNING id_modulo_carrera, orden',
            $parametros,
        );
        $idPorOrden = array_column($creados, 'id_modulo_carrera', 'orden');

        $moduloDeMateria = [];
        foreach ($bloques as $i => $bloque) {
            foreach ($bloque['modulos'] as $materia) {
                $moduloDeMateria[(int) $materia['id_modulo']] = $idPorOrden[$bloque['orden'] ?? $i + 1];
            }
        }

        return $moduloDeMateria;
    }

    public function actualizar(MallaCurricular $malla, array $datos): MallaCurricular
    {
        if (isset($datos['version']) && $datos['version'] !== $malla->version) {
            $this->validarVersionUnica($malla->id_programa, $datos['version'], $malla->id_malla);
        }

        $datos = $this->normalizarBloques($datos);

        return DB::transaction(function () use ($malla, $datos) {
            if (array_key_exists('modulos', $datos)) {
                if ($malla->ofertas()->exists() && $this->cambianModulos($malla, $datos['modulos'])) {
                    throw new ReglaNegocioException(
                        'Esta malla ya se usa en una oferta académica: no se pueden cambiar sus módulos. Cree una nueva versión.'
                    );
                }

                // Sin ofertas, la malla se rearma completa (materias y módulos de la carrera).
                if (! $malla->ofertas()->exists()) {
                    $malla->mallaModulos()->delete();
                    $malla->bloques()->delete();
                    $this->guardarPlan($malla, $datos);
                    $malla->horas_totales = $this->horasTotales($datos['modulos']);
                }
            }

            $malla->fill(array_intersect_key($datos, array_flip(['version', 'estado', 'fecha_vigencia'])))->save();

            return $malla;
        });
    }

    /** Crea una nueva versión copiando las materias (y los módulos de la carrera, si los tiene). */
    public function versionar(MallaCurricular $origen, string $version, ?string $fechaVigencia): MallaCurricular
    {
        $materias = $origen->mallaModulos()->get(['id_modulo', 'orden', 'obligatorio', 'id_modulo_carrera']);
        $datos = [
            'id_programa' => $origen->id_programa,
            'version' => $version,
            'fecha_vigencia' => $fechaVigencia,
            'modulos' => $materias->map->only(['id_modulo', 'orden', 'obligatorio'])->all(),
        ];

        // Solo se copian los módulos de la carrera si todas las materias pertenecen a uno.
        if ($materias->isNotEmpty() && $materias->every(fn ($m) => $m->id_modulo_carrera)) {
            $datos['bloques'] = $origen->bloques()->with('materias')->get()->map(fn (ModuloCarrera $b) => [
                'nombre' => $b->nombre,
                'orden' => $b->orden,
                'modulos' => $b->materias->map->only(['id_modulo', 'obligatorio'])->all(),
            ])->all();
        }

        return $this->crear($datos);
    }

    private function validarVersionUnica(int $idPrograma, string $version, ?int $excepto = null): void
    {
        $existe = MallaCurricular::where('id_programa', $idPrograma)
            ->whereRaw('LOWER(version) = ?', [mb_strtolower($version)])
            ->when($excepto, fn ($q) => $q->where('id_malla', '<>', $excepto))
            ->exists();

        if ($existe) {
            throw new ReglaNegocioException(
                "Este programa ya tiene una malla con la versión «{$version}». Búsquela en la lista y use «Editar» "
                .'para cambiarla, o escriba otra versión (por ejemplo 2026-2). La versión solo se repite entre programas distintos.'
            );
        }
    }

    private function horasTotales(array $modulos): int
    {
        return (int) Modulo::whereIn('id_modulo', array_column($modulos, 'id_modulo'))->sum('horas');
    }

    /** Inserta todas las materias de la malla en UNA consulta, cada una con su módulo de carrera (si tiene). */
    private function guardarModulos(MallaCurricular $malla, array $modulos, array $moduloDeMateria = []): void
    {
        MallaModulo::insert(array_map(fn ($modulo, $i) => [
            'id_malla' => $malla->id_malla,
            'id_modulo' => (int) $modulo['id_modulo'],
            'orden' => $modulo['orden'] ?? $i + 1,
            'obligatorio' => (bool) ($modulo['obligatorio'] ?? true),
            'id_modulo_carrera' => $moduloDeMateria[(int) $modulo['id_modulo']] ?? null,
        ], array_values($modulos), array_keys(array_values($modulos))));
    }

    private function cambianModulos(MallaCurricular $malla, array $modulos): bool
    {
        $actuales = $malla->mallaModulos()->pluck('id_modulo')->sort()->values()->all();
        $nuevos = collect($modulos)->pluck('id_modulo')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $actuales !== $nuevos;
    }
}
