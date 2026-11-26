<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * CU1 · Altas, cambios y roles de los usuarios.
 * Lo comparten el registro público, la gestión del Director y el registro
 * de estudiantes que hace la Secretaria.
 */
class UsuarioService
{
    /** Reglas de validación de los datos personales (comunes a todos los formularios). */
    public static function reglas(?Usuario $usuario = null, bool $conAcceso = true): array
    {
        $id = $usuario?->id_usuario;
        $unico = fn (string $columna) => Rule::unique('usuario', $columna)->ignore($id, 'id_usuario');

        $reglas = [
            'nombres' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[\pL\s\'.-]+$/u'],
            'apellidos' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[\pL\s\'.-]+$/u'],
            'tipo_documento' => ['required', Rule::in(Usuario::TIPOS_DOCUMENTO)],
            'ci' => ['required', 'string', 'min:5', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', $unico('ci')],
            'correo' => ['required', 'email', 'max:150', $unico('correo')],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]{6,30}$/'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1920-01-01'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:150'],
        ];

        if ($conAcceso) {
            $reglas['username'] = ['required', 'string', 'min:4', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', $unico('username')];
            $reglas['password'] = [$usuario ? 'nullable' : 'required', 'string', 'confirmed', self::reglaPassword()];
        }

        return $reglas;
    }

    public static function reglaPassword(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    /** @param  array<int, int>  $idsRoles */
    public function crear(array $datos, array $idsRoles): Usuario
    {
        return DB::transaction(function () use ($datos, $idsRoles) {
            $usuario = Usuario::create([
                ...$this->datosPersonales($datos),
                'username' => $datos['username'],
                'password_hash' => Hash::make($datos['password']),
                'estado' => Usuario::ACTIVO,
                'fecha_registro' => now(),
            ]);

            $this->sincronizarRoles($usuario, $idsRoles);

            return $usuario;
        });
    }

    public function actualizar(Usuario $usuario, array $datos): Usuario
    {
        $cambios = $this->datosPersonales($datos);

        if (! empty($datos['username'])) {
            $cambios['username'] = $datos['username'];
        }

        if (! empty($datos['password'])) {
            $cambios['password_hash'] = Hash::make($datos['password']);
        }

        $usuario->update($cambios);

        return $usuario;
    }

    /**
     * Deja al usuario exactamente con los roles indicados. Los que se quitan
     * no se borran: quedan con activo = false para conservar el historial.
     *
     * @param  array<int, int>  $idsRoles
     */
    public function sincronizarRoles(Usuario $usuario, array $idsRoles): void
    {
        $idsRoles = array_values(array_unique(array_map('intval', $idsRoles)));

        if (empty($idsRoles)) {
            throw new ReglaNegocioException('El usuario debe tener al menos un rol.');
        }

        DB::table('usuario_rol')->where('id_usuario', $usuario->id_usuario)
            ->whereNotIn('id_rol', $idsRoles)->update(['activo' => false]);

        foreach ($idsRoles as $idRol) {
            $existe = DB::table('usuario_rol')->where('id_usuario', $usuario->id_usuario)->where('id_rol', $idRol);

            $existe->exists()
                ? $existe->update(['activo' => true])
                : DB::table('usuario_rol')->insert([
                    'id_usuario' => $usuario->id_usuario, 'id_rol' => $idRol, 'activo' => true, 'fecha_asignacion' => now(),
                ]);
        }
    }

    public function cambiarEstado(Usuario $usuario, string $estado, Usuario $actor): Usuario
    {
        if ($usuario->is($actor)) {
            throw new ReglaNegocioException('No puede cambiar el estado de su propia cuenta.');
        }

        $usuario->update(['estado' => $estado]);

        if ($estado !== Usuario::ACTIVO) {
            $usuario->tokens()->delete();
        }

        return $usuario;
    }

    public function idRol(string $nombre): int
    {
        return Rol::where('nombre', $nombre)->valueOrFail('id_rol');
    }

    private function datosPersonales(array $datos): array
    {
        return array_intersect_key($datos, array_flip([
            'nombres', 'apellidos', 'tipo_documento', 'ci', 'correo', 'telefono', 'direccion', 'fecha_nacimiento', 'lugar_nacimiento',
        ]));
    }
}
