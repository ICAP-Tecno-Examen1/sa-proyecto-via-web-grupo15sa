<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos mínimos para que el sistema funcione:
 *   php artisan db:seed                      -> roles, permisos, menú, métodos de pago y cuentas iniciales
 *   php artisan db:seed --class=DemoSeeder   -> además, datos de demostración (opcional)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolPermisoSeeder::class,
            MenuSeeder::class,
            FinanzasSeeder::class,
            UsuarioSeeder::class,
        ]);
    }
}
