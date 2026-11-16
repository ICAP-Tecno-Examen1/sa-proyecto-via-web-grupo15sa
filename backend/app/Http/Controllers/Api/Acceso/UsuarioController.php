<?php

namespace App\Http\Controllers\Api\Acceso;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU1 · Gestión de usuarios.
 *
 * Dos alcances sobre el mismo recurso:
 *   - /usuarios     -> Director: cualquier usuario, sus roles y su estado.
 *   - /estudiantes  -> Secretaria: solo cuentas con rol Estudiante (alta y datos).
 */
class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->listar($request));
    }

    public function estudiantes(Request $request): JsonResponse
    {
        return response()->json($this->listar($request, Rol::ESTUDIANTE));
    }

    /** Usuarios activos con un rol dado (para combos: docentes, estudiantes). */
    public function porRol(string $rol): JsonResponse
    {
        abort_unless(in_array($rol, ['docentes', 'estudiantes'], true), 404);

        $nombre = $rol === 'docentes' ? Rol::DOCENTE : Rol::ESTUDIANTE;

        return response()->json(
            Usuario::where('estado', Usuario::ACTIVO)
                ->whereHas('rolesActivos', fn ($q) => $q->where('rol.nombre', $nombre))
                ->orderBy('apellidos')->orderBy('nombres')
                ->get(['id_usuario', 'nombres', 'apellidos', 'ci', 'correo'])
        );
    }

    public function show(Usuario $usuario): JsonResponse
    {
        return response()->json($this->detalle($usuario));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            ...UsuarioService::reglas(),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('rol', 'id_rol')],
        ]);

        $usuario = $this->usuarios->crear($datos, $datos['roles']);
        Auditoria::describir("Registró al usuario {$usuario->username}");

        return response()->json($this->detalle($usuario), 201);
    }

    public function storeEstudiante(Request $request): JsonResponse
    {
        $datos = $request->validate(UsuarioService::reglas());

        $usuario = $this->usuarios->crear($datos, [$this->usuarios->idRol(Rol::ESTUDIANTE)]);
        Auditoria::describir("Registró al estudiante {$usuario->nombre_completo}");

        return response()->json($this->detalle($usuario), 201);
    }

    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $datos = $request->validate([
            ...UsuarioService::reglas($usuario),
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('rol', 'id_rol')],
        ]);

        $this->usuarios->actualizar($usuario, $datos);

        if (isset($datos['roles'])) {
            $this->protegerDirector($request, $usuario, $datos['roles']);
            $this->usuarios->sincronizarRoles($usuario, $datos['roles']);
        }

        Auditoria::describir("Actualizó al usuario {$usuario->username}");

        return response()->json($this->detalle($usuario));
    }

    public function updateEstudiante(Request $request, Usuario $usuario): JsonResponse
    {
        // La Secretaria solo puede modificar cuentas que sean únicamente de estudiante.
        $roles = $usuario->rolesActivos()->pluck('rol.nombre');
        abort_unless($roles->all() === [Rol::ESTUDIANTE], 403, 'Solo puede modificar cuentas de estudiantes.');

        $datos = $request->validate(UsuarioService::reglas($usuario));
        $this->usuarios->actualizar($usuario, $datos);
        Auditoria::describir("Actualizó los datos del estudiante {$usuario->nombre_completo}");

        return response()->json($this->detalle($usuario));
    }

    public function cambiarEstado(Request $request, Usuario $usuario): JsonResponse
    {
        $datos = $request->validate(['estado' => ['required', Rule::in([Usuario::ACTIVO, Usuario::INACTIVO])]]);

        $this->usuarios->cambiarEstado($usuario, $datos['estado'], $request->user());
        Auditoria::describir(($datos['estado'] === Usuario::ACTIVO ? 'Activó' : 'Desactivó')." al usuario {$usuario->username}");

        return response()->json($this->detalle($usuario));
    }

    // ------------------------------------------------------------------

    private function listar(Request $request, ?string $soloRol = null)
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in([Usuario::ACTIVO, Usuario::INACTIVO])],
            'id_rol' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'between:5,100'],
        ]);

        return Usuario::query()
            ->with(['roles' => fn ($q) => $q->wherePivot('activo', true)])
            ->when($soloRol, fn ($q) => $q->whereHas('rolesActivos', fn ($r) => $r->where('rol.nombre', $soloRol)))
            ->when($filtros['id_rol'] ?? null, fn ($q, $id) => $q->whereHas('rolesActivos', fn ($r) => $r->where('rol.id_rol', $id)))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])
                    ->orWhereRaw('LOWER(ci) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(correo) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$patron]));
            })
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    private function detalle(Usuario $usuario): Usuario
    {
        return $usuario->refresh()->load(['roles' => fn ($q) => $q->wherePivot('activo', true)]);
    }

    /** Evita que el sistema se quede sin ningún Director (nadie podría gestionar accesos). */
    private function protegerDirector(Request $request, Usuario $usuario, array $idsRoles): void
    {
        $idDirector = $this->usuarios->idRol(Rol::DIRECTOR);

        if (! $usuario->tieneRol(Rol::DIRECTOR) || in_array($idDirector, array_map('intval', $idsRoles), true)) {
            return;
        }

        $otros = Usuario::where('estado', Usuario::ACTIVO)->where('id_usuario', '<>', $usuario->id_usuario)
            ->whereHas('rolesActivos', fn ($q) => $q->where('rol.id_rol', $idDirector))->exists();

        abort_unless($otros, 422, 'No se puede quitar el rol Director al único Director activo del sistema.');
    }
}
