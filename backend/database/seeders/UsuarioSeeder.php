<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Una cuenta inicial por cada actor del sistema. Idempotente (por username).
 * Contraseña de todas las cuentas: Icap2026*   (cámbiela al ingresar).
 */
class UsuarioSeeder extends Seeder
{
    public const PASSWORD = 'Icap2026*';

    /** username => [nombres, apellidos, ci, rol] */
    public const CUENTAS = [
        'director' => ['Roberto', 'Suárez Antelo', '3456781', Rol::DIRECTOR],
        'secretaria' => ['María Elena', 'Vaca Justiniano', '4567812', Rol::SECRETARIA],
        'coord.academico' => ['Jorge Luis', 'Méndez Rojas', '5678123', Rol::COORDINADOR_ACADEMICO],
        'coord.financiero' => ['Patricia', 'Gutiérrez Salvatierra', '6781234', Rol::COORDINADOR_FINANCIERO],
        'docente' => ['Carlos Alberto', 'Rivero Paz', '7812345', Rol::DOCENTE],
        'docente2' => ['Ana Lucía', 'Fernández Cuéllar', '8123456', Rol::DOCENTE],
        'estudiante' => ['Luis Fernando', 'Montaño Égüez', '9234561', Rol::ESTUDIANTE],
    ];

    public function run(): void
    {
        $servicio = app(UsuarioService::class);

        foreach (self::CUENTAS as $username => [$nombres, $apellidos, $ci, $rol]) {
            $usuario = Usuario::firstOrCreate(['username' => $username], [
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'ci' => $ci,
                'tipo_documento' => 'CI',
                'correo' => $username.'@icap.edu.bo',
                'telefono' => '7'.substr($ci, 0, 7),
                'direccion' => 'Santa Cruz de la Sierra',
                'estado' => Usuario::ACTIVO,
                'fecha_registro' => now(),
                'password_hash' => Hash::make(self::PASSWORD),
            ]);

            if ($usuario->wasRecentlyCreated) {
                $servicio->sincronizarRoles($usuario, [$servicio->idRol($rol)]);
            }
        }
    }
}
