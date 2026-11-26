<?php

namespace App\Http\Controllers\Api\Acceso;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\MenuService;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CU1 · Inicio y cierre de sesión, registro público de estudiantes.
 * La API es sin estado: cada sesión es un token personal de Sanctum.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly MenuService $menu,
        private readonly UsuarioService $usuarios,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:100'],
        ]);

        $identificador = mb_strtolower(trim($datos['usuario']));

        $usuario = Usuario::whereRaw('LOWER(username) = ?', [$identificador])
            ->orWhereRaw('LOWER(correo) = ?', [$identificador])
            ->first();

        $motivo = match (true) {
            ! $usuario => 'Usuario inexistente',
            ! $usuario->verificarContrasena($datos['password']) => 'Contraseña incorrecta',
            ! $usuario->estaActivo() => 'Cuenta desactivada',
            ! $usuario->rolActivo() => 'Cuenta sin rol asignado',
            default => null,
        };

        if ($motivo) {
            // Intento fallido: queda en bitácora con la IP y el identificador usado.
            Auditoria::registrar(Bitacora::LOGIN_FALLIDO, $motivo, $usuario, $datos['usuario'], 422);

            $mensaje = in_array($motivo, ['Cuenta desactivada', 'Cuenta sin rol asignado'], true)
                ? 'Su cuenta no está habilitada. Comuníquese con el ICAP.'
                : 'El usuario o la contraseña no son correctos.';

            return response()->json(['message' => $mensaje, 'errors' => ['usuario' => [$mensaje]]], 422);
        }

        $token = $usuario->createToken('spa')->plainTextToken;
        Auditoria::registrar(Bitacora::LOGIN_EXITOSO, 'Inicio de sesión', $usuario, $datos['usuario'], 200);

        return response()->json(['token' => $token, ...$this->sesion($usuario)]);
    }

    /** Registro desde la página web: la cuenta nace con el rol Estudiante. */
    public function registro(Request $request): JsonResponse
    {
        $datos = $request->validate(UsuarioService::reglas());

        $usuario = $this->usuarios->crear($datos, [$this->usuarios->idRol(Rol::ESTUDIANTE)]);
        $token = $usuario->createToken('spa')->plainTextToken;

        Auditoria::describir('Registro público de estudiante: '.$usuario->username);
        Auditoria::registrar(Bitacora::LOGIN_EXITOSO, 'Inicio de sesión tras el registro', $usuario, $usuario->username, 201);

        return response()->json(['token' => $token, ...$this->sesion($usuario)], 201);
    }

    /** Datos de la sesión actual: usuario, roles, permisos del rol activo y menú. */
    public function yo(Request $request): JsonResponse
    {
        return response()->json($this->sesion($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $usuario->currentAccessToken()->delete();

        Auditoria::registrar(Bitacora::LOGOUT, 'Cierre de sesión', $usuario, $usuario->username, 200);

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    private function sesion(Usuario $usuario): array
    {
        return [
            'usuario' => $usuario,
            'roles' => $usuario->rolesActivos()->get(['rol.id_rol', 'rol.nombre', 'rol.descripcion'])->makeHidden('pivot'),
            'rol_activo' => $usuario->rolActivo()?->only(['id_rol', 'nombre']),
            'permisos' => $usuario->permisos(),
            'menu' => $this->menu->paraUsuario($usuario),
        ];
    }
}
