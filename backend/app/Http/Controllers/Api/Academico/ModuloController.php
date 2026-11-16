<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU2 · Módulos o materias (se reutilizan en las mallas de cualquier programa).
 */
class ModuloController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json(
            Modulo::withCount('mallaModulos')
                ->when($f['q'] ?? null, function ($q, $texto) {
                    $patron = '%'.mb_strtolower($texto).'%';
                    $q->where(fn ($w) => $w->whereRaw('LOWER(nombre) LIKE ?', [$patron])->orWhereRaw('LOWER(codigo) LIKE ?', [$patron]));
                })
                ->orderBy('codigo')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $modulo = Modulo::create($this->validar($request));
        Auditoria::describir("Registró el módulo {$modulo->codigo}");

        return response()->json($modulo, 201);
    }

    public function update(Request $request, Modulo $modulo): JsonResponse
    {
        $datos = $this->validar($request, $modulo);

        // Las horas totales de las mallas ya creadas se calcularon con las horas anteriores.
        abort_if(
            (int) $datos['horas'] !== $modulo->horas && $modulo->mallaModulos()->exists(),
            422,
            'No se puede cambiar la carga horaria de un módulo que ya pertenece a una malla curricular.'
        );

        $modulo->update($datos);
        Auditoria::describir("Actualizó el módulo {$modulo->codigo}");

        return response()->json($modulo);
    }

    public function destroy(Modulo $modulo): JsonResponse
    {
        abort_if($modulo->mallaModulos()->exists(), 422, 'El módulo pertenece a una malla curricular y no se puede eliminar.');

        $modulo->delete();
        Auditoria::describir("Eliminó el módulo {$modulo->codigo}");

        return response()->json(['message' => 'Módulo eliminado.']);
    }

    private function validar(Request $request, ?Modulo $modulo = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('modulo', 'codigo')->ignore($modulo?->id_modulo, 'id_modulo')],
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'horas' => ['required', 'integer', 'between:1,1000'],
        ]);
    }
}
