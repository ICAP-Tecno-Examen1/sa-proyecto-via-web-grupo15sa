<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU2 · Catálogo de programas académicos.
 */
class ProgramaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in([Programa::ACTIVO, Programa::INACTIVO])],
        ]);

        return response()->json(
            Programa::withCount('mallas')
                ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
                ->when($f['q'] ?? null, function ($q, $texto) {
                    $patron = '%'.mb_strtolower($texto).'%';
                    $q->where(fn ($w) => $w->whereRaw('LOWER(nombre) LIKE ?', [$patron])->orWhereRaw('LOWER(codigo) LIKE ?', [$patron]));
                })
                ->orderBy('nombre')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $programa = Programa::create($this->validar($request));
        Auditoria::describir("Registró el programa {$programa->codigo}");

        return response()->json($programa, 201);
    }

    public function update(Request $request, Programa $programa): JsonResponse
    {
        $programa->update($this->validar($request, $programa));
        Auditoria::describir("Actualizó el programa {$programa->codigo}");

        return response()->json($programa);
    }

    public function destroy(Programa $programa): JsonResponse
    {
        abort_if($programa->mallas()->exists(), 422, 'El programa tiene mallas curriculares. Puede desactivarlo, pero no eliminarlo.');

        $programa->delete();
        Auditoria::describir("Eliminó el programa {$programa->codigo}");

        return response()->json(['message' => 'Programa eliminado.']);
    }

    private function validar(Request $request, ?Programa $programa = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('programa', 'codigo')->ignore($programa?->id_programa, 'id_programa')],
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'tipo' => ['required', Rule::in(Programa::TIPOS)],
            'estado' => ['required', Rule::in([Programa::ACTIVO, Programa::INACTIVO])],
        ]);
    }
}
