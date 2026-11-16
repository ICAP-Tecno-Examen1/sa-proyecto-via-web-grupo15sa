<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impide operar a un usuario desactivado o sin roles, aunque conserve un token.
 */
class VerificarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->estaActivo()) {
            $usuario->currentAccessToken()?->delete();

            return response()->json(['message' => 'Su cuenta está desactivada. Comuníquese con el ICAP.'], 401);
        }

        if ($usuario && ! $usuario->rolActivo()) {
            return response()->json(['message' => 'Su cuenta no tiene un rol asignado. Comuníquese con el ICAP.'], 403);
        }

        return $next($request);
    }
}
