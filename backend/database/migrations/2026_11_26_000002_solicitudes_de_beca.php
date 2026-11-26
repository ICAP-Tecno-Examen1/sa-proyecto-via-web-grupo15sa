<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Solicitudes de beca con documento de respaldo (database/sql/08_solicitudes_beca.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/08_solicitudes_beca.sql')));
    }

    public function down(): void
    {
        // Intencionalmente vacío: los datos de la base compartida no se borran desde una migración.
    }
};
