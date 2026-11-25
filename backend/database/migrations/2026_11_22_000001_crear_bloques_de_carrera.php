<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulos de la carrera: agrupan 1 o 2 materias dentro de la malla (database/sql/04_bloques_carrera.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/04_bloques_carrera.sql')));
    }

    public function down(): void
    {
        // Intencionalmente vacío: los datos de la base compartida no se borran desde una migración.
    }
};
