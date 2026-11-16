<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea las tablas del script aprobado por el grupo (database/sql/01_esquema_base.sql).
 * Usa IF NOT EXISTS: si la base ya tiene las tablas del primer parcial, no las toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/01_esquema_base.sql')));
    }

    public function down(): void
    {
        // A propósito no se elimina nada: estas tablas contienen los datos del grupo.
    }
};
