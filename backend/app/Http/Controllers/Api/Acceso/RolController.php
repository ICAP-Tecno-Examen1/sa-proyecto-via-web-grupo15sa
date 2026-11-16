<?php

namespace App\Http\Controllers\Api\Acceso;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use App\Models\Rol;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU1 · Roles y matriz de permisos (RBAC).
 * El Director crea roles y decide qué permisos concede a cada uno.
 */
class RolController extends Controller
{
    /** Permisos que el rol Director no puede perder (evita bloquear la administración). */
    private const PERMISOS_PROTEGIDOS = ['usuarios.ver', 'usuarios.gestionar', 'roles.ver', 'roles.gestionar'];

    public function index(): JsonResponse
    {
        $roles = Rol::withCount(['permisos', 'usuarios' => fn ($q) => $q->where('usuario_rol.activo', true)])
            ->with('permisos:permiso.id_permiso')
            ->orderBy('id_rol')->get()
            ->map(fn (Rol $rol) => [
                ...$rol->only(['id_rol', 'nombre', 'descripcion', 'permisos_count', 'usuarios_count']),
                'es_base' => $rol->esBase(),
                'permisos' => $rol->permisos->pluck('id_permiso'),
            ]);

        return response()->json($roles);
    }

    /** Catálogo de permisos agrupado por módulo, para pintar la matriz. */
    public function permisos(): JsonResponse
    {
        return response()->json(
            Permiso::orderBy('modulo')->orderBy('id_permiso')->get()
                ->groupBy('modulo')
                ->map(fn ($permisos, $modulo) => ['modulo' => $modulo, 'permisos' => $permisos->values()])
                ->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $rol = Rol::create(['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null]);
        $rol->permisos()->sync($datos['permisos'] ?? []);

        Auditoria::describir("Creó el rol {$rol->nombre}");

        return response()->json($rol, 201);
    }

    public function update(Request $request, Rol $rol): JsonResponse
    {
        $datos = $this->validar($request, $rol);

        // El nombre de un rol base es el que usa el sistema para identificar al actor.
        if ($rol->esBase() && $datos['nombre'] !== $rol->nombre) {
            abort(422, 'No se puede renombrar un rol base del sistema.');
        }

        $rol->update(['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null]);

        if (array_key_exists('permisos', $datos)) {
            $this->sincronizarPermisos($rol, $datos['permisos']);
        }

        Auditoria::describir("Actualizó el rol {$rol->nombre}");

        return response()->json($rol);
    }

    public function destroy(Rol $rol): JsonResponse
    {
        abort_if($rol->esBase(), 422, 'Los roles base del sistema no se pueden eliminar.');
        abort_if($rol->usuarios()->exists(), 422, 'No se puede eliminar un rol que tiene usuarios asignados.');

        $rol->delete();
        Auditoria::describir("Eliminó el rol {$rol->nombre}");

        return response()->json(['message' => 'Rol eliminado.']);
    }

    // ------------------------------------------------------------------

    private function validar(Request $request, ?Rol $rol = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:100', Rule::unique('rol', 'nombre')->ignore($rol?->id_rol, 'id_rol')],
            'descripcion' => ['nullable', 'string', 'max:300'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['integer', Rule::exists('permiso', 'id_permiso')],
        ]);
    }

    private function sincronizarPermisos(Rol $rol, array $idsPermisos): void
    {
        if ($rol->nombre === Rol::DIRECTOR) {
            $protegidos = Permiso::whereIn('accion', self::PERMISOS_PROTEGIDOS)->pluck('id_permiso')->all();

            if (array_diff($protegidos, array_map('intval', $idsPermisos))) {
                abort(422, 'El rol Director debe conservar los permisos de gestión de usuarios y roles.');
            }
        }

        $rol->permisos()->sync($idsPermisos);
    }
}
