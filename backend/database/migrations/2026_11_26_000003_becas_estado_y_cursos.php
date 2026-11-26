<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estado de las becas y cursos que cubren (database/sql/09_becas_estado_y_cursos.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/09_becas_estado_y_cursos.sql')));
    }

    public function down(): void
    {
        // Intencionalmente vacío: los datos de la base compartida no se borran desde una migración.
    }
};
