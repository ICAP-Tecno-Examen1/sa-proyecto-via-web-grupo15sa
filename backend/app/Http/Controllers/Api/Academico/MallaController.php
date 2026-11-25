<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\MallaCurricular;
use App\Models\ModuloCarrera;
use App\Services\Auditoria;
use App\Services\MallaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU2 · Mallas curriculares y sus versiones.
 */
class MallaController extends Controller
{
    /** Programa, materias (con su módulo de carrera, si lo tienen) y los módulos de la carrera. */
    private const RELACIONES = ['programa:id_programa,codigo,nombre', 'mallaModulos.modulo', 'bloques'];

    public function __construct(private readonly MallaService $mallas) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'id_programa' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in([MallaCurricular::ACTIVO, MallaCurricular::INACTIVO])],
        ]);

        return response()->json(
            MallaCurricular::with(self::RELACIONES)
                ->withCount('ofertas')
                ->when($f['id_programa'] ?? null, fn ($q, $id) => $q->where('id_programa', $id))
                ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
                ->orderBy('id_programa')->orderByDesc('id_malla')->get()
        );
    }

    public function show(MallaCurricular $malla): JsonResponse
    {
        return response()->json($this->detalle($malla));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_programa' => ['required', 'integer', Rule::exists('programa', 'id_programa')],
            ...$this->reglas(),
        ]);

        $malla = $this->mallas->crear($datos);
        Auditoria::describir("Creó la malla versión {$malla->version}");

        return response()->json($this->detalle($malla), 201);
    }

    public function update(Request $request, MallaCurricular $malla): JsonResponse
    {
        $malla = $this->mallas->actualizar($malla, $request->validate($this->reglas()));
        Auditoria::describir("Actualizó la malla versión {$malla->version}");

        return response()->json($this->detalle($malla));
    }

    /** Nueva versión a partir de una malla existente (copia sus módulos). */
    public function versionar(Request $request, MallaCurricular $malla): JsonResponse
    {
        $datos = $request->validate([
            'version' => ['required', 'string', 'max:30'],
            'fecha_vigencia' => ['nullable', 'date'],
        ]);

        $nueva = $this->mallas->versionar($malla, $datos['version'], $datos['fecha_vigencia'] ?? null);
        Auditoria::describir("Creó la versión {$nueva->version} a partir de la versión {$malla->version}");

        return response()->json($this->detalle($nueva), 201);
    }

    private function reglas(): array
    {
        return [
            'version' => ['required', 'string', 'max:30'],
            'fecha_vigencia' => ['nullable', 'date'],
            'estado' => ['required', Rule::in([MallaCurricular::ACTIVO, MallaCurricular::INACTIVO])],
            'modulos' => ['required_without:bloques', 'array', 'min:1'],
            'modulos.*.id_modulo' => ['required', 'integer', 'distinct', Rule::exists('modulo', 'id_modulo')],
            'modulos.*.orden' => ['required', 'integer', 'between:1,100'],
            'modulos.*.obligatorio' => ['required', 'boolean'],
            // Módulos de la carrera (cada uno con 1 o 2 materias). Si se envían, reemplazan a "modulos".
            'bloques' => ['nullable', 'array', 'min:1'],
            'bloques.*.nombre' => ['required', 'string', 'min:3', 'max:200'],
            'bloques.*.orden' => ['required', 'integer', 'between:1,100'],
            'bloques.*.modulos' => ['required', 'array', 'min:1', 'max:'.ModuloCarrera::MAXIMO_MATERIAS],
            'bloques.*.modulos.*.id_modulo' => ['required', 'integer', Rule::exists('modulo', 'id_modulo')],
            'bloques.*.modulos.*.obligatorio' => ['sometimes', 'boolean'],
        ];
    }

    private function detalle(MallaCurricular $malla): MallaCurricular
    {
        // Sin refresh(): el modelo ya tiene sus datos y cada consulta cuenta si la BD está lejos.
        return $malla->load(self::RELACIONES)->loadCount('ofertas');
    }
}
