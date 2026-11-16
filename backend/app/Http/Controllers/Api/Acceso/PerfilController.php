<?php

namespace App\Http\Controllers\Api\Acceso;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * CU1 · Consulta y actualización de los datos personales del propio usuario.
 */
class PerfilController extends Controller
{
    public function actualizar(Request $request, UsuarioService $usuarios): JsonResponse
    {
        $usuario = $request->user();
        $datos = $request->validate(UsuarioService::reglas($usuario, conAcceso: false));

        Auditoria::describir('Actualizó sus datos personales');

        return response()->json($usuarios->actualizar($usuario, $datos));
    }

    public function cambiarPassword(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:password_actual', UsuarioService::reglaPassword()],
        ]);

        if (! Hash::check($datos['password_actual'], (string) $usuario->password_hash)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
                'errors' => ['password_actual' => ['La contraseña actual no es correcta.']],
            ], 422);
        }

        $usuario->update(['password_hash' => Hash::make($datos['password'])]);

        // Se cierran las demás sesiones abiertas con la contraseña anterior.
        $usuario->tokens()->where('id', '<>', $usuario->currentAccessToken()->id)->delete();

        Auditoria::describir('Cambió su contraseña');

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
