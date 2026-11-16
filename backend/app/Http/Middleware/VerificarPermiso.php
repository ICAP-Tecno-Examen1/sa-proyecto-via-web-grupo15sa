<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Matriz de control de acceso (RBAC).
 *
 * Uso en rutas:  ->middleware('permiso:usuarios.ver')
 *                ->middleware('permiso:pagos.propios|pagos.registrar')   (basta uno)
 *
 * El permiso se busca en rol_permiso para el ROL ACTIVO del usuario.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string $permisos): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json(['message' => 'Debe iniciar sesión para continuar.'], 401);
        }

        if (! $usuario->tieneAlgunPermiso(explode('|', $permisos))) {
            return response()->json([
                'message' => 'Su rol no tiene permiso para realizar esta acción.',
                'permiso_requerido' => $permisos,
            ], 403);
        }

        return $next($request);
    }
}
